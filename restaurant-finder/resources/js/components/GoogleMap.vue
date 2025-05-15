<template>
  <div class="card shadow-sm mb-4">
    <div class="card-body p-0">
      <div v-if="mapError" class="alert alert-danger m-3">
        {{ mapError }}
      </div>
      <div class="map-container" ref="mapContainer" style="height: 600px; width: 100%"></div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, watch, onUnmounted } from 'vue';

const props = defineProps({
  restaurants: {
    type: Array,
    default: () => []
  },
  selectedRestaurant: {
    type: Object,
    default: null
  },
  searchLocation: {
    type: Object,
    default: null
  },
  searchRadius: {
    type: Number,
    default: 1000
  },
  searchMode: {
    type: String,
    default: 'keyword'
  }
});

const emit = defineEmits(['select-restaurant', 'close-info-window', 'location-selected']);
const mapContainer = ref(null);
const googleMap = ref(null);
const markers = ref([]);
const infoWindow = ref(null);
const mapError = ref('');
const placesService = ref(null);
const mapListeners = ref([]);
const radiusCircle = ref(null);
const locationMarker = ref(null);

const initMap = () => {
  try {
    console.log('Initializing map...');
    
    if (!window.google || !window.google.maps) {
      mapError.value = 'Google Maps API not loaded. Please check your API key and internet connection.';
      return;
    }
    
    // Bangkok coordinates as default center
    const center = { lat: 13.8055, lng: 100.5312 };
    
    googleMap.value = new window.google.maps.Map(mapContainer.value, {
      center,
      zoom: 12,
      mapTypeControl: false,
      streetViewControl: false,
      fullscreenControl: true,
    });
    
    infoWindow.value = new window.google.maps.InfoWindow();
    
    if (infoWindow.value) {
      const listener = infoWindow.value.addListener('closeclick', () => {
        emit('close-info-window');
      });
      mapListeners.value.push({ target: infoWindow.value, name: 'closeclick', listener });
    }
    
    // Initialize Places service
    if (window.google.maps.places) {
      placesService.value = new window.google.maps.places.PlacesService(googleMap.value);
    }
    
    // Add search box
    const input = document.createElement('input');
    input.className = 'form-control';
    input.type = 'text';
    input.placeholder = 'Search for a location';
    input.style.margin = '10px';
    input.style.width = 'calc(100% - 20px)';
    
    const searchBox = new window.google.maps.places.SearchBox(input);
    googleMap.value.controls[window.google.maps.ControlPosition.TOP_CENTER].push(input);
    
    // Bias the SearchBox results towards current map's viewport
    const boundsListener = googleMap.value.addListener('bounds_changed', () => {
      searchBox.setBounds(googleMap.value.getBounds());
    });
    mapListeners.value.push({ target: googleMap.value, name: 'bounds_changed', listener: boundsListener });
    
    // Listen for the event fired when the user selects a prediction and retrieve
    // more details for that place.
    const placesListener = searchBox.addListener('places_changed', () => {
      const places = searchBox.getPlaces();
      
      if (places.length === 0) {
        return;
      }
      
      // For each place, get the icon, name and location.
      const bounds = new window.google.maps.LatLngBounds();
      
      places.forEach((place) => {
        if (!place.geometry || !place.geometry.location) {
          console.log('Returned place contains no geometry');
          return;
        }
        
        if (props.searchMode === 'nearby') {
          // Set the search location to the selected place
          const lat = place.geometry.location.lat();
          const lng = place.geometry.location.lng();
          emit('location-selected', { lat, lng });
        }
        
        if (place.geometry.viewport) {
          // Only geocodes have viewport.
          bounds.union(place.geometry.viewport);
        } else {
          bounds.extend(place.geometry.location);
        }
      });
      
      googleMap.value.fitBounds(bounds);
    });
    mapListeners.value.push({ target: searchBox, name: 'places_changed', listener: placesListener });
    
    // Add click listener for setting search location in nearby mode
    const clickListener = googleMap.value.addListener('click', (event) => {
      if (props.searchMode === 'nearby') {
        const lat = event.latLng.lat();
        const lng = event.latLng.lng();
        emit('location-selected', { lat, lng });
      }
    });
    mapListeners.value.push({ target: googleMap.value, name: 'click', listener: clickListener });
    
    updateMarkers();
  } catch (error) {
    console.error('Error initializing map:', error);
    mapError.value = `Error initializing map: ${error.message}`;
  }
};

const updateMarkers = () => {
  try {
    if (!googleMap.value) return;
    
    // Clear existing markers
    if (markers.value && Array.isArray(markers.value)) {
      markers.value.forEach(marker => {
        if (marker) marker.setMap(null);
      });
    }
    markers.value = [];
    
    // Add new markers
    if (props.restaurants && Array.isArray(props.restaurants) && props.restaurants.length > 0) {
      console.log(`Adding ${props.restaurants.length} markers to map`);
      
      // Create bounds to fit all markers
      const bounds = new window.google.maps.LatLngBounds();
      
      props.restaurants.forEach(restaurant => {
        if (!restaurant || !restaurant.location) {
          console.warn('Restaurant missing location data:', restaurant);
          return;
        }
        
        const position = {
          lat: parseFloat(restaurant.location.lat),
          lng: parseFloat(restaurant.location.lng)
        };
        
        if (isNaN(position.lat) || isNaN(position.lng)) {
          console.warn('Invalid coordinates for restaurant:', restaurant);
          return;
        }
        
        const marker = new window.google.maps.Marker({
          position,
          map: googleMap.value,
          title: restaurant.name,
          animation: window.google.maps.Animation.DROP
        });
        
        const clickListener = marker.addListener('click', () => {
          emit('select-restaurant', restaurant);
        });
        mapListeners.value.push({ target: marker, name: 'click', listener: clickListener });
        
        markers.value.push(marker);
        bounds.extend(position);
      });
      
      // Only adjust bounds if we have markers
      if (markers.value.length > 0) {
        // If we're in nearby mode and have a search location, include it in the bounds
        if (props.searchMode === 'nearby' && props.searchLocation) {
          bounds.extend(props.searchLocation);
        }
        
        googleMap.value.fitBounds(bounds);
        
        // Don't zoom in too far
        const listener = googleMap.value.addListener('idle', () => {
          if (googleMap.value.getZoom() > 16) {
            googleMap.value.setZoom(16);
          }
          window.google.maps.event.removeListener(listener);
        });
      }
    } else {
      console.log('No restaurants to display on map');
      
      // If we're in nearby mode and have a search location, center on it
      if (props.searchMode === 'nearby' && props.searchLocation) {
        googleMap.value.setCenter(props.searchLocation);
        googleMap.value.setZoom(14);
      }
    }
  } catch (error) {
    console.error('Error updating markers:', error);
    mapError.value = `Error updating map markers: ${error.message}`;
  }
};

const updateInfoWindow = () => {
  try {
    if (!props.selectedRestaurant || !infoWindow.value || !googleMap.value) return;
    
    if (!props.selectedRestaurant.location) {
      console.warn('Selected restaurant has no location data:', props.selectedRestaurant);
      return;
    }
    
    // Include distance in the info window if available
    const distanceInfo = props.selectedRestaurant.distanceText 
      ? `<p class="mb-1 small"><i class="bi bi-geo"></i> ${props.selectedRestaurant.distanceText}</p>` 
      : '';
    
    const content = `
      <div class="info-window">
        <h5 class="mb-1">${props.selectedRestaurant.name}</h5>
        <p class="mb-1 text-muted">${props.selectedRestaurant.address}</p>
        <div class="d-flex align-items-center mb-1">
          <span class="badge bg-primary me-2">${props.selectedRestaurant.cuisine}</span>
          <small>${props.selectedRestaurant.rating}/5 ⭐</small>
        </div>
        ${distanceInfo}
        <p class="mb-0 small">${props.selectedRestaurant.openingHours}</p>
      </div>
    `;
    
    infoWindow.value.setContent(content);
    infoWindow.value.setPosition({
      lat: parseFloat(props.selectedRestaurant.location.lat),
      lng: parseFloat(props.selectedRestaurant.location.lng)
    });
    
    infoWindow.value.open(googleMap.value);
  } catch (error) {
    console.error('Error updating info window:', error);
    mapError.value = `Error showing restaurant details: ${error.message}`;
  }
};

const updateSearchLocation = () => {
  try {
    if (!googleMap.value || !props.searchLocation) return;
    
    // Clear existing location marker
    if (locationMarker.value) {
      locationMarker.value.setMap(null);
      locationMarker.value = null;
    }
    
    // Create new location marker
    locationMarker.value = new window.google.maps.Marker({
      position: props.searchLocation,
      map: googleMap.value,
      icon: {
        path: window.google.maps.SymbolPath.CIRCLE,
        scale: 10,
        fillColor: '#4285F4',
        fillOpacity: 1,
        strokeColor: '#ffffff',
        strokeWeight: 2
      },
      zIndex: 1000 // Ensure it's above restaurant markers
    });
    
    // Update radius circle
    updateRadiusCircle();
    
    // Center map on search location
    googleMap.value.setCenter(props.searchLocation);
  } catch (error) {
    console.error('Error updating search location:', error);
    mapError.value = `Error updating search location: ${error.message}`;
  }
};

const updateRadiusCircle = () => {
  try {
    if (!googleMap.value || !props.searchLocation) return;
    
    // Clear existing radius circle
    if (radiusCircle.value) {
      radiusCircle.value.setMap(null);
      radiusCircle.value = null;
    }
    
    // Create new radius circle
    radiusCircle.value = new window.google.maps.Circle({
      strokeColor: '#4285F4',
      strokeOpacity: 0.8,
      strokeWeight: 2,
      fillColor: '#4285F4',
      fillOpacity: 0.1,
      map: googleMap.value,
      center: props.searchLocation,
      radius: props.searchRadius,
      zIndex: 1 // Ensure it's below markers
    });
    
    // Fit map to circle bounds
    googleMap.value.fitBounds(radiusCircle.value.getBounds());
  } catch (error) {
    console.error('Error updating radius circle:', error);
    mapError.value = `Error updating search radius: ${error.message}`;
  }
};

const loadGoogleMapsScript = () => {
  return new Promise((resolve, reject) => {
    try {
      // If Google Maps is already loaded
      if (window.google && window.google.maps) {
        console.log('Google Maps already loaded');
        resolve();
        return;
      }
      
      // Get API key from Laravel window object
      const apiKey = window.Laravel?.googleMapsApiKey;
      console.log('Loading Google Maps with API Key:', apiKey ? 'Key exists' : 'No key found');
      
      if (!apiKey) {
        reject(new Error('Google Maps API key not found. Please check your .env file.'));
        return;
      }
      
      // Create script element
      const script = document.createElement('script');
      script.src = `https://maps.googleapis.com/maps/api/js?key=${apiKey}&libraries=places`;
      script.async = true;
      script.defer = true;
      
      script.onload = () => {
        console.log('Google Maps script loaded successfully');
        resolve();
      };
      
      script.onerror = (error) => {
        console.error('Google Maps script failed to load:', error);
        reject(new Error('Failed to load Google Maps API'));
      };
      
      // Add script to document
      document.head.appendChild(script);
    } catch (error) {
      console.error('Error in loadGoogleMapsScript:', error);
      reject(error);
    }
  });
};

// Clean up event listeners to prevent memory leaks
const cleanupListeners = () => {
  mapListeners.value.forEach(({ target, name, listener }) => {
    if (target && listener) {
      window.google.maps.event.removeListener(listener);
    }
  });
  mapListeners.value = [];
};

onMounted(async () => {
  try {
    await loadGoogleMapsScript();
    initMap();
  } catch (error) {
    console.error('Failed to initialize map:', error);
    mapError.value = `Failed to load Google Maps: ${error.message}`;
  }
});

onUnmounted(() => {
  cleanupListeners();
});

// Watch for changes in restaurants
watch(() => props.restaurants, (newVal) => {
  if (newVal && Array.isArray(newVal) && googleMap.value) {
    console.log('Restaurants changed, updating markers');
    updateMarkers();
  }
}, { deep: true });

// Watch for changes in selected restaurant
watch(() => props.selectedRestaurant, (newVal) => {
  if (newVal && googleMap.value) {
    console.log('Selected restaurant changed, updating info window');
    updateInfoWindow();
  }
});

// Watch for changes in search location
watch(() => props.searchLocation, (newVal) => {
  if (newVal && googleMap.value && props.searchMode === 'nearby') {
    console.log('Search location changed, updating map');
    updateSearchLocation();
  }
}, { deep: true });

// Watch for changes in search radius
watch(() => props.searchRadius, (newVal) => {
  if (newVal && googleMap.value && props.searchLocation && props.searchMode === 'nearby') {
    console.log('Search radius changed, updating circle');
    updateRadiusCircle();
  }
});

// Watch for changes in search mode
watch(() => props.searchMode, (newVal) => {
  if (newVal === 'nearby') {
    // Clear existing markers when switching to nearby mode
    if (markers.value && Array.isArray(markers.value)) {
      markers.value.forEach(marker => {
        if (marker) marker.setMap(null);
      });
    }
    markers.value = [];
    
    // Update search location if available
    if (props.searchLocation) {
      updateSearchLocation();
    }
  } else {
    // Clear radius circle and location marker when switching to keyword mode
    if (radiusCircle.value) {
      radiusCircle.value.setMap(null);
      radiusCircle.value = null;
    }
    
    if (locationMarker.value) {
      locationMarker.value.setMap(null);
      locationMarker.value = null;
    }
  }
});
</script>

<style scoped>
.info-window {
  padding: 8px;
  max-width: 250px;
}

.map-container {
  background-color: #f8f9fa;
}
</style>
