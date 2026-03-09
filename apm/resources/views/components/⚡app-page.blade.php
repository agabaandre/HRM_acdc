<?php

use Livewire\Component;

/**
 * Generic Livewire page wrapper. Renders any Blade view with the given data.
 * Use: @livewire('app-page', ['view' => 'pages.home-content', 'data' => compact('user', 'permissions')])
 */
new class extends Component
{
    public string $view = '';

    /** @var array<string, mixed> */
    public array $data = [];

    public function mount(string $view = '', array $data = []): void
    {
        $this->view = $view;
        $this->data = $data;
    }
};
?>

<div>
    @if($view && view()->exists($view))
        @include($view, $data)
    @endif
</div>
