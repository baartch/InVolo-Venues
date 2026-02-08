"use strict";
var __awaiter = (this && this.__awaiter) || function (thisArg, _arguments, P, generator) {
    function adopt(value) { return value instanceof P ? value : new P(function (resolve) { resolve(value); }); }
    return new (P || (P = Promise))(function (resolve, reject) {
        function fulfilled(value) { try { step(generator.next(value)); } catch (e) { reject(e); } }
        function rejected(value) { try { step(generator["throw"](value)); } catch (e) { reject(e); } }
        function step(result) { result.done ? resolve(result.value) : adopt(result.value).then(fulfilled, rejected); }
        step((generator = generator.apply(thisArg, _arguments || [])).next());
    });
};
const LEAFLET_SCRIPT_SRC = 'https://unpkg.com/leaflet@1.7.1/dist/leaflet.js';
const LEAFLET_SCRIPT_INTEGRITY = 'sha512-XQoYMqMTK8LvdxXYG3nZ448hOEQiglfqkJs1NOQV44cWnUrBc8PkAOcXy20w0vlaXaVUearIOBhiXZ5V3ynxwA==';
const loadLeaflet = () => {
    if (window.L) {
        return Promise.resolve();
    }
    return new Promise((resolve, reject) => {
        const existingScript = document.querySelector(`script[src="${LEAFLET_SCRIPT_SRC}"]`);
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
const MAP_CONTAINER_ID = 'mapid';
const SEARCH_INPUT_ID = 'waypoint-search';
const SEARCH_RESULTS_ID = 'search-results';
const WAYPOINTS_URL = 'app/routes/waypoints/index.php';
const SEARCH_RESULT_CLASS = 'dropdown-item';
const SELECTED_CLASS = 'is-active';
const DROPDOWN_ACTIVE_CLASS = 'is-active';
const SEARCH_API_URL = 'app/routes/venues/search.php';
const SEARCH_MIN_LENGTH = 2;
const SEARCH_DEBOUNCE_MS = 500;
const DEFAULT_LAT = 50.394512;
const DEFAULT_LNG = 11.480713;
const DEFAULT_ZOOM = 6;
const FOCUS_ZOOM = 15;
const MARKER_COLOR = 60;
const MIN_FETCH_ZOOM = 9;
const URL_LAT_PARAM = 'lat';
const URL_LNG_PARAM = 'lng';
const URL_ZOOM_PARAM = 'zoom';
const ZOOM_HINT_ID = 'map-zoom-hint';
const MAP_VIEW_STORAGE_KEY = 'mapView';
let map;
let markerLayer;
let hasUrlView = false;
const allWaypoints = [];
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
const createMarkerIcon = () => L.divIcon({
    className: 'my-custom-pin',
    iconAnchor: [0, 24],
    labelAnchor: [-6, 0],
    popupAnchor: [0, -36],
    html: `<span style="background-color: rgb(0, ${MARKER_COLOR}, ${MARKER_COLOR / 2}); ${markerHtmlStyles}" />`
});
const parseWaypointElement = (wpt) => {
    var _a, _b, _c, _d, _e, _f;
    const name = ((_b = (_a = wpt.getElementsByTagName('name')[0]) === null || _a === void 0 ? void 0 : _a.textContent) === null || _b === void 0 ? void 0 : _b.trim()) || 'Unknown venue';
    const url = ((_d = (_c = wpt.getElementsByTagName('url')[0]) === null || _c === void 0 ? void 0 : _c.textContent) === null || _d === void 0 ? void 0 : _d.trim()) || '';
    const description = ((_f = (_e = wpt.getElementsByTagName('desc')[0]) === null || _e === void 0 ? void 0 : _e.textContent) === null || _f === void 0 ? void 0 : _f.trim()) || '';
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
const createWaypointMarker = (waypoint, icon) => {
    const marker = L.marker([waypoint.lat, waypoint.lon], { icon }).addTo(markerLayer !== null && markerLayer !== void 0 ? markerLayer : map);
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
    return Object.assign(Object.assign({}, waypoint), { marker,
        popup });
};
const focusWaypoint = (waypoint, searchInput, searchMenu, searchDropdown) => {
    map.setView([waypoint.lat, waypoint.lon], FOCUS_ZOOM);
    waypoint.popup.openPopup();
    if (searchInput) {
        searchInput.value = waypoint.name;
    }
    if (searchMenu) {
        searchMenu.classList.add('is-hidden');
    }
    searchDropdown === null || searchDropdown === void 0 ? void 0 : searchDropdown.classList.remove(DROPDOWN_ACTIVE_CLASS);
};
const focusSearchResult = (result, searchInput, searchMenu, searchDropdown) => {
    map.setView([result.lat, result.lng], FOCUS_ZOOM);
    if (searchInput) {
        searchInput.value = result.name;
    }
    if (searchMenu) {
        searchMenu.classList.add('is-hidden');
    }
    searchDropdown === null || searchDropdown === void 0 ? void 0 : searchDropdown.classList.remove(DROPDOWN_ACTIVE_CLASS);
};
const clearWaypoints = () => {
    allWaypoints.length = 0;
    if (markerLayer) {
        markerLayer.clearLayers();
    }
};
const getZoomHint = () => document.getElementById(ZOOM_HINT_ID);
const setZoomHintVisible = (isVisible) => {
    const hint = getZoomHint();
    if (!hint) {
        return;
    }
    hint.classList.toggle('is-hidden', !isVisible);
    hint.setAttribute('aria-hidden', isVisible ? 'false' : 'true');
};
const buildWaypointsUrl = () => {
    if (!map || !map._loaded) {
        return null;
    }
    const zoom = map.getZoom();
    if (zoom < MIN_FETCH_ZOOM) {
        setZoomHintVisible(true);
        return null;
    }
    setZoomHintVisible(false);
    const bounds = map.getBounds();
    const params = new URLSearchParams({
        minLat: bounds.getSouth().toFixed(6),
        maxLat: bounds.getNorth().toFixed(6),
        minLng: bounds.getWest().toFixed(6),
        maxLng: bounds.getEast().toFixed(6)
    });
    return `${WAYPOINTS_URL}?${params.toString()}`;
};
function parseWaypoints() {
    return __awaiter(this, void 0, void 0, function* () {
        const url = buildWaypointsUrl();
        if (!url) {
            clearWaypoints();
            return;
        }
        const response = yield fetch(url);
        if (!response.ok) {
            throw new Error('Failed to load waypoints');
        }
        const parser = new DOMParser();
        const xmlDoc = parser.parseFromString(yield response.text(), 'text/xml');
        const waypointNodes = Array.from(xmlDoc.getElementsByTagName('wpt'));
        const markerIcon = createMarkerIcon();
        clearWaypoints();
        waypointNodes.forEach((node) => {
            const parsed = parseWaypointElement(node);
            if (!parsed) {
                return;
            }
            const waypoint = createWaypointMarker(parsed, markerIcon);
            allWaypoints.push(waypoint);
        });
    });
}
const updateMapUrl = () => {
    if (!map || !map._loaded) {
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
const getStoredView = () => {
    try {
        const raw = window.localStorage.getItem(MAP_VIEW_STORAGE_KEY);
        if (!raw) {
            return null;
        }
        const parsed = JSON.parse(raw);
        if (typeof parsed.lat !== 'number' || typeof parsed.lng !== 'number' || typeof parsed.zoom !== 'number') {
            return null;
        }
        return { lat: parsed.lat, lng: parsed.lng, zoom: parsed.zoom };
    }
    catch (error) {
        console.warn('Failed to read stored map view', error);
        return null;
    }
};
const storeMapView = () => {
    if (!map || !map._loaded) {
        return;
    }
    const center = map.getCenter();
    const zoom = map.getZoom();
    try {
        window.localStorage.setItem(MAP_VIEW_STORAGE_KEY, JSON.stringify({
            lat: Number(center.lat.toFixed(6)),
            lng: Number(center.lng.toFixed(6)),
            zoom
        }));
    }
    catch (error) {
        console.warn('Failed to store map view', error);
    }
};
const applyUrlView = () => {
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
const applyStoredView = () => {
    if (hasUrlView) {
        return;
    }
    const storedView = getStoredView();
    if (!storedView) {
        return;
    }
    hasUrlView = true;
    map.setView([storedView.lat, storedView.lng], storedView.zoom);
};
function initializeSearch() {
    var _a, _b;
    const searchInput = document.getElementById(SEARCH_INPUT_ID);
    const searchMenu = document.getElementById(SEARCH_RESULTS_ID);
    const searchResults = (_a = searchMenu === null || searchMenu === void 0 ? void 0 : searchMenu.querySelector('.dropdown-content')) !== null && _a !== void 0 ? _a : null;
    const searchDropdown = (_b = searchMenu === null || searchMenu === void 0 ? void 0 : searchMenu.closest('.dropdown')) !== null && _b !== void 0 ? _b : null;
    if (!searchInput || !searchMenu || !searchResults || !searchDropdown) {
        console.error('Search elements not found');
        return;
    }
    let selectedIndex = -1;
    let filteredWaypoints = [];
    let searchMatches = [];
    let activeRequest = 0;
    let debounceId = null;
    const clearSearchResults = () => {
        searchMenu.classList.add('is-hidden');
        searchDropdown.classList.remove(DROPDOWN_ACTIVE_CLASS);
        searchResults.innerHTML = '';
        selectedIndex = -1;
    };
    const selectItem = (index) => {
        const items = searchResults.querySelectorAll(`.${SEARCH_RESULT_CLASS}`);
        items.forEach(item => item.classList.remove(SELECTED_CLASS));
        if (index >= 0 && index < items.length) {
            selectedIndex = index;
            const selected = items[index];
            selected.classList.add(SELECTED_CLASS);
            selected.scrollIntoView({ block: 'nearest' });
            return;
        }
        selectedIndex = -1;
    };
    const navigateToSelected = () => {
        if (selectedIndex < 0) {
            return;
        }
        if (searchMatches[selectedIndex]) {
            focusSearchResult(searchMatches[selectedIndex], searchInput, searchMenu, searchDropdown);
            selectedIndex = -1;
            return;
        }
        if (filteredWaypoints[selectedIndex]) {
            focusWaypoint(filteredWaypoints[selectedIndex], searchInput, searchMenu, searchDropdown);
            selectedIndex = -1;
        }
    };
    const renderResults = () => {
        if (searchMatches.length === 0 && filteredWaypoints.length === 0) {
            searchResults.innerHTML = `<div class="dropdown-item">No venues found</div>`;
            searchMenu.classList.remove('is-hidden');
            searchDropdown.classList.add(DROPDOWN_ACTIVE_CLASS);
            return;
        }
        if (searchMatches.length > 0) {
            searchResults.innerHTML = searchMatches.map((result, index) => `
        <a class="${SEARCH_RESULT_CLASS}" data-index="${index}">
          ${result.name}
        </a>
      `).join('');
            searchMenu.classList.remove('is-hidden');
            searchDropdown.classList.add(DROPDOWN_ACTIVE_CLASS);
            return;
        }
        searchResults.innerHTML = filteredWaypoints.map((wp, index) => `
      <a class="${SEARCH_RESULT_CLASS}" data-index="${index}">
        ${wp.name}
      </a>
    `).join('');
        searchMenu.classList.remove('is-hidden');
        searchDropdown.classList.add(DROPDOWN_ACTIVE_CLASS);
    };
    const performSearch = (query) => __awaiter(this, void 0, void 0, function* () {
        const requestId = ++activeRequest;
        if (query.length < SEARCH_MIN_LENGTH) {
            searchMatches = [];
            renderResults();
            return;
        }
        try {
            const response = yield fetch(`${SEARCH_API_URL}?q=${encodeURIComponent(query)}`);
            if (!response.ok) {
                return;
            }
            const data = (yield response.json());
            if (requestId !== activeRequest) {
                return;
            }
            searchMatches = data;
            renderResults();
        }
        catch (error) {
            console.error('Failed to search venues', error);
        }
    });
    searchInput.addEventListener('input', (event) => {
        const target = event.target;
        const searchTerm = target.value.trim();
        if (!searchTerm) {
            filteredWaypoints = [];
            searchMatches = [];
            clearSearchResults();
            return;
        }
        filteredWaypoints = allWaypoints.filter(waypoint => waypoint.name.toLowerCase().includes(searchTerm.toLowerCase()));
        if (debounceId) {
            window.clearTimeout(debounceId);
        }
        debounceId = window.setTimeout(() => {
            void performSearch(searchTerm);
        }, SEARCH_DEBOUNCE_MS);
        renderResults();
    });
    searchResults.addEventListener('click', (event) => {
        const target = event.target.closest(`.${SEARCH_RESULT_CLASS}`);
        if (!target || !target.dataset.index) {
            return;
        }
        const index = Number(target.dataset.index);
        if (Number.isNaN(index)) {
            return;
        }
        if (searchMatches[index]) {
            focusSearchResult(searchMatches[index], searchInput, searchMenu, searchDropdown);
            return;
        }
        if (filteredWaypoints[index]) {
            focusWaypoint(filteredWaypoints[index], searchInput, searchMenu, searchDropdown);
        }
    });
    searchInput.addEventListener('keydown', (event) => {
        if (searchMatches.length === 0 && filteredWaypoints.length === 0) {
            return;
        }
        switch (event.key) {
            case 'ArrowDown':
                event.preventDefault();
                selectItem(selectedIndex < Math.max(searchMatches.length, filteredWaypoints.length) - 1 ? selectedIndex + 1 : 0);
                break;
            case 'ArrowUp':
                event.preventDefault();
                selectItem(selectedIndex > 0 ? selectedIndex - 1 : Math.max(searchMatches.length, filteredWaypoints.length) - 1);
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
    document.addEventListener('click', (event) => {
        const target = event.target;
        if (!searchInput.contains(target) && !searchMenu.contains(target)) {
            clearSearchResults();
        }
    });
    document.addEventListener('keydown', (event) => {
        if ((event.ctrlKey || event.metaKey) && event.key === 'k') {
            event.preventDefault();
            searchInput.focus();
            searchInput.select();
        }
    });
}
function initializeMap() {
    return __awaiter(this, void 0, void 0, function* () {
        yield loadLeaflet();
        map = L.map(MAP_CONTAINER_ID);
        markerLayer = L.layerGroup().addTo(map);
        L.tileLayer('https://api.mapbox.com/styles/v1/{id}/tiles/{z}/{x}/{y}?access_token={accessToken}', {
            attribution: 'Map data &copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors, Imagery © <a href="https://www.mapbox.com/">Mapbox</a>',
            maxZoom: 18,
            id: 'mapbox/streets-v11',
            tileSize: 512,
            zoomOffset: -1,
            accessToken: 'pk.eyJ1IjoibTByY2gzbCIsImEiOiJjbWtwbHA3ZzQwZjU1M2JyMnJyaDMzZW04In0.-w5O8qGkQj7YrxIFx-lunQ'
        }).addTo(map);
        applyUrlView();
        applyStoredView();
        if (!hasUrlView) {
            map.setView([DEFAULT_LAT, DEFAULT_LNG], DEFAULT_ZOOM);
        }
        map.whenReady(() => {
            map.invalidateSize();
            updateMapUrl();
            storeMapView();
            void parseWaypoints();
        });
        map.on('moveend', () => {
            updateMapUrl();
            storeMapView();
            void parseWaypoints();
        });
        initializeSearch();
    });
}
void initializeMap();
