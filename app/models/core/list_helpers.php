<?php

function buildListColumn(string $label, ?string $key = null, ?callable $render = null, bool $isHtml = false, ?string $sortKey = null): array
{
    return [
        'label' => $label,
        'key' => $key,
        'render' => $render,
        'isHtml' => $isHtml,
        'sortKey' => $sortKey
    ];
}

function buildDetailRow(string $label, $value, bool $isHtml = false): array
{
    return [
        'label' => $label,
        'value' => $value,
        'isHtml' => $isHtml
    ];
}
