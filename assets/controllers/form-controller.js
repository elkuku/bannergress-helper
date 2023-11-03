import {Controller} from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['lat', 'lon', 'zoom', 'result',
        'resultStatus', 'dataStatus', 'resultMission', 'resultMissionStatus'];

    static values = {
        url: String,
        urlData: String,
        urlMission: String,
    }

    map;
    markers;

    connect() {
        this.map = L.map('map').setView([this.latTarget.innerHTML, this.lonTarget.innerHTML], this.zoomTarget.innerHTML);

        L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 19,
            attribution: '© OpenStreetMap'
        }).addTo(this.map);

        this.markers = L.layerGroup()
            .addTo(this.map);

        this.map.on('dragend', function (e) {
            this.refresh();
        }.bind(this))

        this.map.on('zoomend', function (e) {
            this.refresh();
        }.bind(this));

        this.refresh()
    }

    async query() {
        this.resultStatusTarget.classList.add('spinner-grow')
        this.resultTarget.innerHTML = '';

        const center = this.map.getCenter()
        const params = new URLSearchParams({
            lat: center.lat,
            lon: center.lng,
        });
        const response = await fetch(`${this.urlValue}?${params.toString()}`);
        this.resultTarget.innerHTML = await response.text();
        this.resultStatusTarget.classList.remove('spinner-grow')

        this.dataStatusTarget.innerHTML = '';
        const response2 = await fetch(`${this.urlDataValue}?${params.toString()}`);
        const dataS = await response2.text();
        const data = JSON.parse(dataS)
        //console.log(data)

        this.markers.clearLayers()

        for (let i = 0; i < data.length; i++) {
            //console.log(data[i])
            const marker = L.marker([data[i].startLatitude, data[i].startLongitude]);
            marker.bindPopup(
                '<b>'+data[i].title+'</b><br/>'
                +'Missions: '+data[i].numberOfMissions
            )
            this.markers.addLayer(marker);
        }

        //this.dataStatusTarget.innerHTML = dataS;
        //this.dataStatusTarget.innerHTML = this.urlDataValue;


    }

    async queryMission(e) {
        //console.log(e.params)
        //console.log(e.params.id)
        this.resultMissionStatusTarget.classList.add('spinner-grow')
        this.resultMissionTarget.innerHTML = '';

        const params = new URLSearchParams({
            id: e.params.id,
        });
        console.log(this.urlMissionValue)
        const response = await fetch(`${this.urlMissionValue}?${params.toString()}`);
        this.resultMissionTarget.innerHTML = await response.text();
        this.resultMissionStatusTarget.classList.remove('spinner-grow')
    }

    refresh() {
        const center = this.map.getCenter()
        this.latTarget.innerHTML = center.lat;
        this.lonTarget.innerHTML = center.lng;
        this.zoomTarget.innerHTML = this.map.getZoom();
    }
}
