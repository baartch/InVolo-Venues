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
const WAYPOINTS_URL = 'routes/waypoints/index.php';
const SEARCH_RESULT_CLASS = 'search-result-item';
const SELECTED_CLASS = 'selected';
const DEFAULT_ZOOM = 8;
const FOCUS_ZOOM = 15;
const MARKER_COLOR = 60;
let map;
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
    return Object.assign(Object.assign({}, waypoint), { marker,
        popup });
};
const focusWaypoint = (waypoint, searchInput, searchResults) => {
    map.setView([waypoint.lat, waypoint.lon], FOCUS_ZOOM);
    waypoint.popup.openPopup();
    if (searchInput) {
        searchInput.value = waypoint.name;
    }
    if (searchResults) {
        searchResults.style.display = 'none';
    }
};
function parseWaypoints() {
    return __awaiter(this, void 0, void 0, function* () {
        const response = yield fetch(WAYPOINTS_URL);
        if (!response.ok) {
            throw new Error('Failed to load waypoints');
        }
        const parser = new DOMParser();
        const xmlDoc = parser.parseFromString(yield response.text(), 'text/xml');
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
            map.fitBounds(bounds, { padding: [40, 40] });
        }
        else {
            map.setView([0, 0], DEFAULT_ZOOM);
        }
    });
}
function initializeSearch() {
    const searchInput = document.getElementById(SEARCH_INPUT_ID);
    const searchResults = document.getElementById(SEARCH_RESULTS_ID);
    if (!searchInput || !searchResults) {
        console.error('Search elements not found');
        return;
    }
    let selectedIndex = -1;
    let filteredWaypoints = [];
    const clearSearchResults = () => {
        searchResults.style.display = 'none';
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
        if (selectedIndex >= 0 && selectedIndex < filteredWaypoints.length) {
            focusWaypoint(filteredWaypoints[selectedIndex], searchInput, searchResults);
            selectedIndex = -1;
        }
    };
    const renderResults = () => {
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
    searchInput.addEventListener('input', (event) => {
        const target = event.target;
        const searchTerm = target.value.toLowerCase().trim();
        if (!searchTerm) {
            filteredWaypoints = [];
            clearSearchResults();
            return;
        }
        filteredWaypoints = allWaypoints.filter(waypoint => waypoint.name.toLowerCase().includes(searchTerm));
        renderResults();
    });
    searchResults.addEventListener('click', (event) => {
        const target = event.target.closest(`.${SEARCH_RESULT_CLASS}`);
        if (!target || !target.dataset.index) {
            return;
        }
        const index = Number(target.dataset.index);
        if (Number.isNaN(index) || !filteredWaypoints[index]) {
            return;
        }
        focusWaypoint(filteredWaypoints[index], searchInput, searchResults);
    });
    searchInput.addEventListener('keydown', (event) => {
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
    document.addEventListener('click', (event) => {
        const target = event.target;
        if (!searchInput.contains(target) && !searchResults.contains(target)) {
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
        L.tileLayer('https://api.mapbox.com/styles/v1/{id}/tiles/{z}/{x}/{y}?access_token={accessToken}', {
            attribution: 'Map data &copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors, Imagery © <a href="https://www.mapbox.com/">Mapbox</a>',
            maxZoom: 18,
            id: 'mapbox/streets-v11',
            tileSize: 512,
            zoomOffset: -1,
            accessToken: 'pk.eyJ1IjoibTByY2gzbCIsImEiOiJjbWtwbHA3ZzQwZjU1M2JyMnJyaDMzZW04In0.-w5O8qGkQj7YrxIFx-lunQ'
        }).addTo(map);
        try {
            yield parseWaypoints();
        }
        catch (error) {
            console.error('Failed to initialize waypoints', error);
        }
        initializeSearch();
    });
}
void initializeMap();
