<?php

namespace App\Services;

use DOMDocument;
use DOMElement;
use DOMNode;
use Throwable;

/**
 * Whitelist-based SVG sanitizer.
 *
 * An SVG uploaded as a file is untrusted XML. Emitting it raw via {!! !!}
 * lets <script>, <foreignObject>, on* handlers, and javascript: URLs in
 * href/xlink:href execute in the browser. This service strips everything
 * outside a strict safe subset before the markup reaches the page.
 *
 * Called at the RENDER boundary, not just at upload — this means already-
 * stored bad data is also neutralized on the next request. A single
 * request-level cache keeps the DOMDocument cost near-zero for pages
 * that render many icons.
 */
class SvgSanitizerService
{
    /**
     * Elements allowed to remain. Everything else is stripped.
     * Notably absent: script, style, foreignObject, iframe, embed,
     * object, animate, set, animateTransform, animateMotion.
     */
    private const ALLOWED_ELEMENTS = [
        'svg', 'g', 'defs', 'symbol', 'use', 'title', 'desc',
        'path', 'circle', 'ellipse', 'line', 'polyline', 'polygon', 'rect',
        'text', 'tspan', 'textpath',
        'lineargradient', 'radialgradient', 'stop',
        'mask', 'clippath', 'pattern',
        'filter',
        'feblend', 'fecolormatrix', 'fecomponenttransfer', 'fecomposite',
        'feconvolvematrix', 'fediffuselighting', 'fedisplacementmap',
        'fedistantlight', 'fedropshadow', 'feflood',
        'fefunca', 'fefuncb', 'fefuncg', 'fefuncr',
        'fegaussianblur', 'feimage', 'femerge', 'femergenode',
        'femorphology', 'feoffset', 'fepointlight', 'fespecularlighting',
        'fespotlight', 'fetile', 'feturbulence',
    ];

    /**
     * Attributes allowed to remain. Everything else is stripped —
     * including all on* event handlers, which are rejected explicitly
     * before this list is consulted.
     */
    private const ALLOWED_ATTRIBUTES = [
        // Geometry
        'x', 'y', 'x1', 'y1', 'x2', 'y2', 'cx', 'cy', 'r', 'rx', 'ry',
        'width', 'height', 'd', 'points',
        // Presentation
        'fill', 'fill-opacity', 'fill-rule', 'stroke', 'stroke-width',
        'stroke-linecap', 'stroke-linejoin', 'stroke-dasharray',
        'stroke-dashoffset', 'stroke-opacity', 'stroke-miterlimit',
        'opacity', 'transform', 'viewbox', 'preserveaspectratio',
        'class', 'id', 'style',
        // Text
        'font-family', 'font-size', 'font-weight', 'font-style',
        'text-anchor', 'dominant-baseline', 'letter-spacing',
        // Gradient / pattern
        'offset', 'stop-color', 'stop-opacity', 'gradientunits',
        'gradienttransform', 'spreadmethod',
        'patternunits', 'patterncontentunits', 'patterntransform',
        'clippathunits', 'maskunits', 'maskcontentunits',
        'filterunits', 'primitiveunits',
        // Filter primitives
        'in', 'in2', 'result', 'stddeviation', 'values', 'tablevalues',
        'slope', 'intercept', 'amplitude', 'exponent',
        'operator', 'k1', 'k2', 'k3', 'k4', 'mode', 'radius',
        'targetx', 'targety', 'edgemode', 'kernelmatrix', 'divisor',
        'bias', 'scale', 'xchannelselector', 'ychannelselector',
        // References
        'href', 'xlink:href',
        // Namespace
        'xmlns', 'xmlns:xlink',
    ];

    /** Per-process cache. Same (svg, class) pair → same output. */
    private static array $cache = [];

    /**
     * @param  string  $svg    Raw SVG markup.
     * @param  string  $class  Optional class to merge onto the root <svg>.
     * @return string          Safe SVG markup, or '' if the input was
     *                         unparseable / not SVG.
     */
    public function sanitize(string $svg, string $class = ''): string
    {
        $svg = trim($svg);
        if ($svg === '') {
            return '';
        }

        $cacheKey = md5($svg) . '|' . $class;
        if (isset(self::$cache[$cacheKey])) {
            return self::$cache[$cacheKey];
        }

        // Cheap shape check before paying for the DOMDocument.
        if (stripos($svg, '<svg') === false) {
            return self::$cache[$cacheKey] = '';
        }

        $prev = libxml_use_internal_errors(true);

        try {
            $doc = new DOMDocument();

            // LIBXML_NONET prevents external entity resolution — closes the
            // XXE door before sanitization even begins.
            if (! $doc->loadXML($svg, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING)) {
                return self::$cache[$cacheKey] = '';
            }

            $root = $doc->documentElement;
            if (! $root instanceof DOMElement
                || strtolower($root->localName) !== 'svg') {
                return self::$cache[$cacheKey] = '';
            }

            $this->scrubNode($root);

            // Force the correct namespace on the output root.
            $root->setAttribute('xmlns', 'http://www.w3.org/2000/svg');

            // Merge the caller's class in.
            if ($class !== '') {
                $existing = $root->getAttribute('class');
                $root->setAttribute('class', trim($existing . ' ' . $class));
            }

            $out = $doc->saveXML($root);

            return self::$cache[$cacheKey] = ($out === false ? '' : $out);
        } catch (Throwable) {
            return self::$cache[$cacheKey] = '';
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($prev);
        }
    }

    private function scrubNode(DOMNode $node): void
    {
        $children = [];
        foreach ($node->childNodes as $child) {
            $children[] = $child;
        }

        foreach ($children as $child) {
            // Drop comments and processing instructions — no legitimate
            // use for them in an icon and they're a vector for parser
            // confusion.
            if ($child->nodeType === XML_COMMENT_NODE
                || $child->nodeType === XML_PI_NODE) {
                $node->removeChild($child);
                continue;
            }

            if (! $child instanceof DOMElement) {
                continue; // Plain text nodes are fine.
            }

            if (! in_array(strtolower($child->localName), self::ALLOWED_ELEMENTS, true)) {
                $node->removeChild($child);
                continue;
            }

            $this->scrubAttributes($child);
            $this->scrubNode($child);
        }
    }

    private function scrubAttributes(DOMElement $el): void
    {
        $toRemove = [];

        foreach ($el->attributes as $attr) {
            $name  = strtolower($attr->nodeName);
            $value = (string) $attr->nodeValue;

            // Any on* event handler → gone. No exceptions.
            if (str_starts_with($name, 'on')) {
                $toRemove[] = $attr->nodeName;
                continue;
            }

            if (! in_array($name, self::ALLOWED_ATTRIBUTES, true)) {
                $toRemove[] = $attr->nodeName;
                continue;
            }

            // href / xlink:href — allow ONLY fragment references (#id).
            // This blocks javascript:, data:text/html, and remote URLs.
            if ($name === 'href' || $name === 'xlink:href') {
                if (! str_starts_with(strtolower(trim($value)), '#')) {
                    $toRemove[] = $attr->nodeName;
                    continue;
                }
            }

            // style — reject url(...), expression(...), javascript:.
            if ($name === 'style') {
                $lower = strtolower($value);
                if (str_contains($lower, 'url(')
                    || str_contains($lower, 'expression(')
                    || str_contains($lower, 'javascript:')) {
                    $toRemove[] = $attr->nodeName;
                }
            }
        }

        foreach ($toRemove as $name) {
            $el->removeAttribute($name);
        }
    }
}