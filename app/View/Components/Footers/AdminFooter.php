<?php

namespace App\View\Components\Footers;

use Illuminate\View\Component;

class AdminFooter extends Component
{
    /** @phpstan-var view-string */
    public string $view;

    public function __construct(string $view = 'components.footers.admin.superadmin-footer')
    {
        $this->view = $view;
    }

    public function render()
    {
        return view($this->view);
    }
}