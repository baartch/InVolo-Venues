<?php
function getCurrentTheme(?string $userTheme = null, array $allowedThemes = ['forest', 'dracula']): string
{
    if ($userTheme !== null && $userTheme !== '' && in_array($userTheme, $allowedThemes, true)) {
        return $userTheme;
    }

    return 'forest';
}
