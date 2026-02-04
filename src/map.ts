// Type definitions for Leaflet (basic types needed for this project)
declare const L: any;

type LeafletLoader = () => Promise<void>;

const LEAFLET_SCRIPT_SRC = 'https://unpkg.com/leaflet@1.7.1/dist/leaflet.js';
const LEAFLET_SCRIPT_INTEGRITY = 'sha512-XQoYMqMTK8LvdxXYG3nZ448hOEQiglfqkJs1NOQV44cWnUrBc8PkAOcXy20w0vlaXaVUearIOBhiXZ5V3ynxwA==';

const loadLeaflet: LeafletLoader = (): Promise<void> => {
  if ((window as typeof window & { L?: unknown }).L) {
    return Promise.resolve();
  }

  return new Promise((resolve, reject) => {
    const existingScript = document.querySelector<HTMLScriptElement>(`script[src="${LEAFLET_SCRIPT_SRC}"]`);
    if (existingScript) {
      existingScript.addEventListener('load', () => resolve());
      existingScript.addEventListener('error', () => reject(new Error('Failed to load Leaflet')));
      return;
    }

    const script = document.createElement('script');
    script.src = LEAFLET_SCRIPT_SRC;
    script.integrity = LEAFLET_SCRIPT_INTEGRITY;
    script.crossOrigin = '';
    script.addEventListener('load', () => resolve());
    script.addEventListener('error', () => reject(new Error('Failed to load Leaflet')));
    document.head.appendChild(script);
  });
};

interface Waypoint {
  name: string;
  url: string;
  description: string;
  lat: number;
  lon: number;
  marker: any;
  popup: any;
}

const MAP_CONTAINER_ID = 'mapid';
const SEARCH_INPUT_ID = 'waypoint-search';
const SEARCH_RESULTS_ID = 'search-results';
const WAYPOINTS_URL = 'routes/waypoints/index.php';
const SEARCH_RESULT_CLASS = 'search-result-item';
const SELECTED_CLASS = 'selected';
const DEFAULT_ZOOM = 8;
const FOCUS_ZOOM = 15;
const MARKER_COLOR = 60;
const URL_LAT_PARAM = 'lat';
const URL_LNG_PARAM = 'lng';
const URL_ZOOM_PARAM = 'zoom';

let map: any;
let hasUrlView = false;
const allWaypoints: Waypoint[] = [];

const markerHtmlStyles = `
  width: 1rem;
  height: 1rem;
  display: block;
  left: -0.5rem;
  top: 0rem;
  position: relative;
  border-radius: 1rem 1rem 0;
  transform: rotate(45deg);
  border: 1px solid #FFFFFF;`;

const createMarkerIcon = (): any => L.divIcon({
  className: 'my-custom-pin',
  iconAnchor: [0, 24],
  labelAnchor: [-6, 0],
  popupAnchor: [0, -36],
  html: `<span style="background-color: rgb(0, ${MARKER_COLOR}, ${MARKER_COLOR / 2}); ${markerHtmlStyles}" />`
});

const parseWaypointElement = (wpt: Element): Waypoint | null => {
  const name = wpt.getElementsByTagName('name')[0]?.textContent?.trim() || 'Unknown venue';
  const url = wpt.getElementsByTagName('url')[0]?.textContent?.trim() || '';
  const description = wpt.getElementsByTagName('desc')[0]?.textContent?.trim() || '';
  const lat = Number(wpt.getAttribute('lat'));
  const lon = Number(wpt.getAttribute('lon'));

  if (Number.isNaN(lat) || Number.isNaN(lon)) {
    return null;
  }

  return {
    name,
    url,
    description,
    lat,
    lon,
    marker: null,
    popup: null
  };
};

const createWaypointMarker = (waypoint: Waypoint, icon: any): Waypoint => {
  const marker = L.marker([waypoint.lat, waypoint.lon], { icon }).addTo(map);
  const descriptionHtml = waypoint.description
    ? `<div class="venue-description">${waypoint.description
        .split('\n')
        .map(line => `<div>${line}</div>`)
        .join('')}</div>`
    : '';
  const popup = marker.bindPopup(`
    <div>
      <h3>${waypoint.name}</h3>
      ${waypoint.url ? `<div><a href="${waypoint.url}" target="_blank" rel="noopener noreferrer">${waypoint.url}</a></div>` : ''}
      ${descriptionHtml}
    </div>
  `);

  return {
    ...waypoint,
    marker,
    popup
  };
};

const focusWaypoint = (waypoint: Waypoint, searchInput?: HTMLInputElement, searchResults?: HTMLDivElement): void => {
  map.setView([waypoint.lat, waypoint.lon], FOCUS_ZOOM);
  waypoint.popup.openPopup();

  if (searchInput) {
    searchInput.value = waypoint.name;
  }

  if (searchResults) {
    searchResults.style.display = 'none';
  }
};

async function parseWaypoints(): Promise<void> {
  const response = await fetch(WAYPOINTS_URL);

  if (!response.ok) {
    throw new Error('Failed to load waypoints');
  }

  const parser = new DOMParser();
  const xmlDoc = parser.parseFromString(await response.text(), 'text/xml');
  const waypointNodes = Array.from(xmlDoc.getElementsByTagName('wpt'));
  const markerIcon = createMarkerIcon();
  const bounds = L.latLngBounds([]);

  waypointNodes.forEach((node) => {
    const parsed = parseWaypointElement(node);
    if (!parsed) {
      return;
    }

    const waypoint = createWaypointMarker(parsed, markerIcon);
    allWaypoints.push(waypoint);
    bounds.extend([waypoint.lat, waypoint.lon]);
  });

  if (allWaypoints.length > 0) {
    if (!hasUrlView) {
      map.fitBounds(bounds, { padding: [40, 40] });
    }
  } else {
    if (!hasUrlView) {
      map.setView([0, 0], DEFAULT_ZOOM);
    }
  }
}

const updateMapUrl = (): void => {
  if (!map) {
    return;
  }

  const center = map.getCenter();
  const zoom = map.getZoom();
  const params = new URLSearchParams(window.location.search);

  params.set(URL_LAT_PARAM, center.lat.toFixed(6));
  params.set(URL_LNG_PARAM, center.lng.toFixed(6));
  params.set(URL_ZOOM_PARAM, String(zoom));

  const newUrl = `${window.location.pathname}?${params.toString()}`;
  window.history.replaceState({}, '', newUrl);
};

const applyUrlView = (): void => {
  const params = new URLSearchParams(window.location.search);
  const latParam = params.get(URL_LAT_PARAM);
  const lngParam = params.get(URL_LNG_PARAM);
  const zoomParam = params.get(URL_ZOOM_PARAM);

  if (!latParam || !lngParam || !zoomParam) {
    return;
  }

  const lat = Number.parseFloat(latParam);
  const lng = Number.parseFloat(lngParam);
  const zoom = Number.parseInt(zoomParam, 10);

  if (Number.isNaN(lat) || Number.isNaN(lng) || Number.isNaN(zoom)) {
    return;
  }

  hasUrlView = true;
  map.setView([lat, lng], zoom);
};

function initializeSearch(): void {
  const searchInput = document.getElementById(SEARCH_INPUT_ID) as HTMLInputElement | null;
  const searchResults = document.getElementById(SEARCH_RESULTS_ID) as HTMLDivElement | null;

  if (!searchInput || !searchResults) {
    console.error('Search elements not found');
    return;
  }

  let selectedIndex = -1;
  let filteredWaypoints: Waypoint[] = [];

  const clearSearchResults = (): void => {
    searchResults.style.display = 'none';
    searchResults.innerHTML = '';
    selectedIndex = -1;
  };

  const selectItem = (index: number): void => {
    const items = searchResults.querySelectorAll(`.${SEARCH_RESULT_CLASS}`);

    items.forEach(item => item.classList.remove(SELECTED_CLASS));

    if (index >= 0 && index < items.length) {
      selectedIndex = index;
      const selected = items[index] as HTMLElement;
      selected.classList.add(SELECTED_CLASS);
      selected.scrollIntoView({ block: 'nearest' });
      return;
    }

    selectedIndex = -1;
  };

  const navigateToSelected = (): void => {
    if (selectedIndex >= 0 && selectedIndex < filteredWaypoints.length) {
      focusWaypoint(filteredWaypoints[selectedIndex], searchInput, searchResults);
      selectedIndex = -1;
    }
  };

  const renderResults = (): void => {
    if (filteredWaypoints.length === 0) {
      searchResults.innerHTML = `<div class="${SEARCH_RESULT_CLASS}">No venues found</div>`;
      searchResults.style.display = 'block';
      return;
    }

    searchResults.innerHTML = filteredWaypoints.map((wp, index) => `
      <div class="${SEARCH_RESULT_CLASS}" data-index="${index}">
        ${wp.name}
      </div>
    `).join('');

    searchResults.style.display = 'block';
  };

  searchInput.addEventListener('input', (event: Event) => {
    const target = event.target as HTMLInputElement;
    const searchTerm = target.value.toLowerCase().trim();

    if (!searchTerm) {
      filteredWaypoints = [];
      clearSearchResults();
      return;
    }

    filteredWaypoints = allWaypoints.filter(waypoint =>
      waypoint.name.toLowerCase().includes(searchTerm)
    );

    renderResults();
  });

  searchResults.addEventListener('click', (event: Event) => {
    const target = (event.target as HTMLElement).closest(`.${SEARCH_RESULT_CLASS}`) as HTMLElement | null;
    if (!target || !target.dataset.index) {
      return;
    }

    const index = Number(target.dataset.index);
    if (Number.isNaN(index) || !filteredWaypoints[index]) {
      return;
    }

    focusWaypoint(filteredWaypoints[index], searchInput, searchResults);
  });

  searchInput.addEventListener('keydown', (event: KeyboardEvent) => {
    if (filteredWaypoints.length === 0) {
      return;
    }

    switch (event.key) {
      case 'ArrowDown':
        event.preventDefault();
        selectItem(selectedIndex < filteredWaypoints.length - 1 ? selectedIndex + 1 : 0);
        break;
      case 'ArrowUp':
        event.preventDefault();
        selectItem(selectedIndex > 0 ? selectedIndex - 1 : filteredWaypoints.length - 1);
        break;
      case 'Enter':
        event.preventDefault();
        navigateToSelected();
        break;
      case 'Escape':
        clearSearchResults();
        break;
    }
  });

  document.addEventListener('click', (event: Event) => {
    const target = event.target as HTMLElement;
    if (!searchInput.contains(target) && !searchResults.contains(target)) {
      clearSearchResults();
    }
  });

  document.addEventListener('keydown', (event: KeyboardEvent) => {
    if ((event.ctrlKey || event.metaKey) && event.key === 'k') {
      event.preventDefault();
      searchInput.focus();
      searchInput.select();
    }
  });
}

async function initializeMap(): Promise<void> {
  await loadLeaflet();
  map = L.map(MAP_CONTAINER_ID);
  applyUrlView();

  L.tileLayer('https://api.mapbox.com/styles/v1/{id}/tiles/{z}/{x}/{y}?access_token={accessToken}', {
    attribution: 'Map data &copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors, Imagery © <a href="https://www.mapbox.com/">Mapbox</a>',
    maxZoom: 18,
    id: 'mapbox/streets-v11',
    tileSize: 512,
    zoomOffset: -1,
    accessToken: 'pk.eyJ1IjoibTByY2gzbCIsImEiOiJjbWtwbHA3ZzQwZjU1M2JyMnJyaDMzZW04In0.-w5O8qGkQj7YrxIFx-lunQ'
  }).addTo(map);

  try {
    await parseWaypoints();
  } catch (error) {
    console.error('Failed to initialize waypoints', error);
  }

  map.on('moveend', updateMapUrl);
  updateMapUrl();
  initializeSearch();
}

void initializeMap();
