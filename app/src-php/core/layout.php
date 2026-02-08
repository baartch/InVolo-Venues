<?php
require_once __DIR__ . '/security_headers.php';

function renderPageStart(string $title, array $options = []): void
{
    // Set security headers for all pages
    setSecurityHeaders();
    
    // Don't cache sensitive pages
    setNoCacheHeaders();
    
    global $currentUser;
    $includeSidebar = $options['includeSidebar'] ?? true;
    $bodyClass = $options['bodyClass'] ?? 'has-background-grey-dark has-text-light is-flex is-flex-direction-column is-fullheight';
    $extraStyles = $options['extraStyles'] ?? [];
    $extraScripts = $options['extraScripts'] ?? [];
    $leaflet = $options['leaflet'] ?? false;

    echo "<!DOCTYPE html>\n";
    echo "<html lang=\"en\">\n";
    echo "<head>\n";
    echo "  <meta charset=\"UTF-8\">\n";
    echo "  <meta name=\"viewport\" content=\"width=device-width, initial-scale=1.0\">\n";
    $appName = 'BooKing';
    $pageTitle = $title === '' ? $appName : $appName . ' - ' . $title;

    echo "  <base href=\"" . BASE_PATH . "/\">\n";
    echo "  <title>{$pageTitle}</title>\n";

    if ($leaflet) {
        echo "  <link rel=\"stylesheet\" href=\"https://unpkg.com/leaflet@1.7.1/dist/leaflet.css\" integrity=\"sha512-xodZBNTC5n17Xt2atTPuE1HxjVMSvLVW9ocqUKLsCC5CXdbqCmblAshOMAS6/keqq/sMZMZ19scR4PsZChSR7A==\" crossorigin=\"\" />\n";
    }

    echo "  <link rel=\"stylesheet\" href=\"https://cdn.jsdelivr.net/npm/bulma@1.0.4/css/bulma.min.css\">\n";
    echo "  <link rel=\"stylesheet\" href=\"https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css\" integrity=\"sha512-DTOQO9RWCH3ppGqcWaEA1BIZOC6xxalwEsw9c2QQeAIftl+Vegovlnee1c9QX4TctnWMn13TZye+giMm8e2LwA==\" crossorigin=\"anonymous\" referrerpolicy=\"no-referrer\">\n";

    foreach ($extraStyles as $styleUrl) {
        echo "  <link rel=\"stylesheet\" href=\"{$styleUrl}\">\n";
    }


    foreach ($extraScripts as $scriptTag) {
        echo $scriptTag . "\n";
    }

    echo "</head>\n";
    echo "<body class=\"{$bodyClass}\">\n";

    if ($includeSidebar) {
        echo "  <div class=\"columns is-gapless app-layout is-flex-grow-1\">\n";
        require __DIR__ . '/../../partials/sidebar.php';
        echo "\n";
        echo "    <main class=\"column main-content is-flex is-flex-direction-column\">\n";
    } else {
        echo "  <main class=\"is-flex-grow-1\">\n";
    }
}

function renderPageEnd(array $options = []): void
{
    $includeSidebar = $options['includeSidebar'] ?? true;
    $afterMain = $options['afterMain'] ?? '';

    if ($includeSidebar) {
        echo "    </main>\n";
        if ($afterMain !== '') {
            echo $afterMain . "\n";
        }
        echo "  </div>\n";
    } else {
        echo "  </main>\n";
    }

    echo "</body>\n";
    echo "</html>\n";
}
