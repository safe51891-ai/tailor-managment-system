@php($theme = $shopBranding->theme())
<style>
:root {
    --shop-teal: {{ $theme['primary_color'] }};
    --shop-teal-dark: {{ $theme['primary_hover'] }};
    --shop-teal-soft: {{ $theme['primary_soft'] }};
    --shop-action-text: {{ $theme['primary_text'] }};
    --shop-action-hover-text: {{ $theme['primary_hover_text'] }};
    --shop-sidebar: {{ $theme['sidebar_color'] }};
    --shop-sidebar-text: {{ $theme['sidebar_text'] }};
    --shop-sidebar-muted: {{ $theme['sidebar_muted'] }};
    --shop-sidebar-hover: {{ $theme['sidebar_hover'] }};
    --shop-sidebar-active: {{ $theme['sidebar_active'] }};
    --shop-link: {{ $theme['link_color'] }};
    --shop-canvas-text: {{ $theme['canvas_text'] }};
    --shop-canvas-muted: {{ $theme['canvas_muted'] }};
    --shop-canvas: {{ $theme['background_color'] }};
}
</style>
