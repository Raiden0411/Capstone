<?php

namespace App\View\Components\Headers;

use Illuminate\View\Component;

class AdminHeader extends Component
{
    /** @phpstan-var view-string */
    public string $view;

    public function __construct(string $view = 'components.headers.admin.superadmin-header')
    {
        $this->view = $view;
    }

    public function render()
    {
        return view($this->view);
    }
}