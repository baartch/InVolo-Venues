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
    $bodyClass = $options['bodyClass'] ?? 'map-page';
    $extraStyles = $options['extraStyles'] ?? [];
    $extraScripts = $options['extraScripts'] ?? [];
    $leaflet = $options['leaflet'] ?? false;
    $theme = $options['theme'] ?? 'forest';
    $themeHref = BASE_PATH . '/public/css/themes/' . $theme . '.css';

    echo "<!DOCTYPE html>\n";
    echo "<html lang=\"en\">\n";
    echo "<head>\n";
    echo "  <meta charset=\"UTF-8\">\n";
    echo "  <meta name=\"viewport\" content=\"width=device-width, initial-scale=1.0\">\n";
    echo "  <base href=\"" . BASE_PATH . "/\">\n";
    echo "  <title>{$title}</title>\n";

    if ($leaflet) {
        echo "  <link rel=\"stylesheet\" href=\"https://unpkg.com/leaflet@1.7.1/dist/leaflet.css\" integrity=\"sha512-xodZBNTC5n17Xt2atTPuE1HxjVMSvLVW9ocqUKLsCC5CXdbqCmblAshOMAS6/keqq/sMZMZ19scR4PsZChSR7A==\" crossorigin=\"\" />\n";
    }

    echo "  <link rel=\"stylesheet\" href=\"" . BASE_PATH . "/public/css/styles.css\">\n";
    echo "  <link rel=\"stylesheet\" href=\"{$themeHref}\">\n";

    foreach ($extraStyles as $styleUrl) {
        echo "  <link rel=\"stylesheet\" href=\"{$styleUrl}\">\n";
    }


    foreach ($extraScripts as $scriptTag) {
        echo $scriptTag . "\n";
    }

    echo "</head>\n";
    echo "<body class=\"{$bodyClass}\">\n";

    if ($includeSidebar) {
        echo "  <div class=\"app-layout\">\n";
        require __DIR__ . '/../partials/sidebar.php';
        echo "\n";
        echo "    <main class=\"main-content\">\n";
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
    }

    echo "</body>\n";
    echo "</html>\n";
}
