<x-dynamic-component
    :component="$getFieldWrapperView()"
    :field="$field"
>
    @php
        $latStatePath = $field->getLatitudeStatePath();
        $lngStatePath = $field->getLongitudeStatePath();
        $livewire = $field->getLivewire();
        // FIX: Prepend 'data.' to get the value from the form state
        $lat = data_get($livewire, 'data.' . $latStatePath);
        $lng = data_get($livewire, 'data.' . $lngStatePath);
    @endphp

    <div
        wire:ignore
        x-data="mapPicker({
            lat: @js($lat),
            lng: @js($lng),
            latStatePath: @js($latStatePath),
            lngStatePath: @js($lngStatePath)
        })"
        x-init="init()"
        style="z-index: 0;"
        class="w-full h-96 rounded-lg"
        id="map"
    ></div>

    @once
        <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
        <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

        <script>
            function mapPicker({ lat, lng, latStatePath, lngStatePath }) {
                return {
                    map: null,
                    marker: null,
                    lat: lat,
                    lng: lng,

                    init() {
                        // Set the initial view based on provided lat/lng
                        this.map = L.map(this.$el).setView([this.lat || 55.75, this.lng || 37.61], this.lat && this.lng ? 13 : 5);

                        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                            attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
                        }).addTo(this.map);

                        // Add a marker if lat/lng are available
                        if (this.lat && this.lng) {
                            this.marker = L.marker([this.lat, this.lng]).addTo(this.map);
                        }

                        this.map.on('click', (e) => {
                            const { lat, lng } = e.latlng;
                            // Update the form fields when map is clicked
                            this.$wire.set(latStatePath, lat.toFixed(6));
                            this.$wire.set(lngStatePath, lng.toFixed(6));
                        });

                        // Watch for changes in form fields to update the map
                        this.$wire.$watch(latStatePath, (value) => {
                            this.lat = value;
                            this.updateMarker();
                        });

                        this.$wire.$watch(lngStatePath, (value) => {
                            this.lng = value;
                            this.updateMarker();
                        });
                    },

                    updateMarker() {
                        if (!this.lat || !this.lng) return;

                        const newLatLng = [parseFloat(this.lat), parseFloat(this.lng)];
                        if (this.marker) {
                            this.marker.setLatLng(newLatLng);
                        } else {
                            this.marker = L.marker(newLatLng).addTo(this.map);
                        }
                        this.map.setView(newLatLng, this.map.getZoom() < 10 ? 13 : this.map.getZoom());
                    }
                }
            }
        </script>
    @endonce
</x-dynamic-component>
