
// Initialize Google Map after coordinates are fetched
function initMap(latitude, longitude) {
    const shopLocation = { lat:latitude, lng: longitude };
    
    const map = new google.maps.Map(document.getElementById("map"), {
        zoom: 15,
        center: shopLocation,
    });
 
    // Place marker on shop location
    const marker = new google.maps.Marker({
        position: shopLocation,
        map: map,
        title: "Shop Location",
    });


  // Load existing shops
  fetch("/contact.php")
    .then(res => res.json())
    .then(data => {
      data.forEach(shop => {
        new google.maps.Marker({
          position: shopLocation = { lat: parseFloat(shop.latitude), lng: parseFloat(shop.longitude) },
          map,
          title: shop.name
        });
      });
    });
    shopLocation = { lat: parseFloat(shop.latitude), lng: parseFloat(shop.longitude) };
}
// Load the coordinates and map after page is loaded
window.onload = initMap;