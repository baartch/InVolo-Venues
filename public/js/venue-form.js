"use strict";
const qsVenueForm = (selector, scope = document) => scope.querySelector(selector);
const initVenueFormMapbox = () => {
    const form = qsVenueForm('#mapbox_search_form');
    if (!form) {
        return;
    }
    const addressButton = qsVenueForm('#address_mapbox_button');
    const addressInput = qsVenueForm('#address');
    const cityInput = qsVenueForm('#city');
    const countrySelect = qsVenueForm('#country');
    const hiddenAddress = qsVenueForm('#mapbox_address');
    const hiddenCity = qsVenueForm('#mapbox_city');
    const hiddenCountry = qsVenueForm('#mapbox_country');
    if (!addressInput || !cityInput) {
        return;
    }
    const syncMap = [
        { inputId: 'name', hiddenId: 'mapbox_name' },
        { inputId: 'postal_code', hiddenId: 'mapbox_postal_code' },
        { inputId: 'state', hiddenId: 'mapbox_state' },
        { inputId: 'latitude', hiddenId: 'mapbox_latitude' },
        { inputId: 'longitude', hiddenId: 'mapbox_longitude' },
        { inputId: 'type', hiddenId: 'mapbox_type' },
        { inputId: 'contact_email', hiddenId: 'mapbox_contact_email' },
        { inputId: 'contact_phone', hiddenId: 'mapbox_contact_phone' },
        { inputId: 'contact_person', hiddenId: 'mapbox_contact_person' },
        { inputId: 'capacity', hiddenId: 'mapbox_capacity' },
        { inputId: 'website', hiddenId: 'mapbox_website' },
        { inputId: 'notes', hiddenId: 'mapbox_notes' }
    ];
    const getHiddenInput = (hiddenId) => qsVenueForm(`#${hiddenId}`);
    const updateState = () => {
        const address = addressInput.value.trim();
        const city = cityInput.value.trim();
        const isReady = address !== '' && city !== '';
        if (addressButton) {
            addressButton.disabled = !isReady;
            addressButton.classList.toggle('is-disabled', !isReady);
            if (!isReady) {
                addressButton.setAttribute('aria-disabled', 'true');
            }
            else {
                addressButton.removeAttribute('aria-disabled');
            }
        }
    };
    const syncFields = () => {
        if (hiddenAddress) {
            hiddenAddress.value = addressInput.value;
        }
        if (hiddenCity) {
            hiddenCity.value = cityInput.value;
        }
        if (hiddenCountry) {
            hiddenCountry.value = countrySelect ? countrySelect.value : '';
        }
        syncMap.forEach(({ inputId, hiddenId }) => {
            const input = qsVenueForm(`#${inputId}`);
            const hidden = getHiddenInput(hiddenId);
            if (!input || !hidden) {
                return;
            }
            if (input.tagName.toLowerCase() === 'select') {
                hidden.value = input.value;
            }
            else {
                hidden.value = input.value;
            }
        });
    };
    const validateAndSync = (event) => {
        syncFields();
        const address = addressInput.value.trim();
        const city = cityInput.value.trim();
        if (address === '' || city === '') {
            event === null || event === void 0 ? void 0 : event.preventDefault();
            updateState();
        }
    };
    [addressInput, cityInput].forEach((input) => {
        input.addEventListener('input', () => {
            syncFields();
            updateState();
        });
        input.addEventListener('change', () => {
            syncFields();
            updateState();
        });
    });
    if (countrySelect) {
        countrySelect.addEventListener('change', syncFields);
    }
    syncMap.forEach(({ inputId }) => {
        const input = qsVenueForm(`#${inputId}`);
        if (!input) {
            return;
        }
        input.addEventListener('input', syncFields);
        input.addEventListener('change', syncFields);
    });
    if (addressButton) {
        addressButton.addEventListener('mousedown', () => {
            syncFields();
        });
        addressButton.addEventListener('click', (event) => {
            validateAndSync(event);
        });
    }
    form.addEventListener('submit', (event) => {
        validateAndSync(event);
    });
    syncFields();
    updateState();
};
document.addEventListener('DOMContentLoaded', () => {
    initVenueFormMapbox();
});
