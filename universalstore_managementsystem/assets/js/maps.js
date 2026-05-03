// Initialize and add the map
function initMap() {
  // Shop location (latitude and longitude)
  const shopLocation = { lat: 3.848, lng: 11.5021 }; // Replace with your shop's coordinates

  // Create the map centered on the shop
  const map = new google.maps.Map(document.getElementById("map"), {
    zoom: 15,
    center: shopLocation,
     draggable: true,
  });
  
  // Try to get the user's location
  if (navigator.geolocation) {
    navigator.geolocation.getCurrentPosition(
      (position) => {
        const userLocation = {
          lat: position.coords.latitude,
          lng: position.coords.longitude,
          
        };
        
        // Center the map on the user's location
        map.setCenter(userLocation);


  // Add a marker for the shop
  const marker = new google.maps.Marker({
    position: shopLocation,
    map: map,
    title: "Our Shop",
  });

  //Sending coordinates to php backend (via AJAX/fetch)
  sendLocationToServer(userLocation);
},
      () => {
        alert("Geolocation failed. Showing default location.");
      }
    );
  } else {
    alert("Geolocation is not supported by this browser.");
  }
}



// Load the map after the page loads
window.onload = initMap;


  // Function to send location to PHP using fetch (POST)
  function sendLocationToServer(coords) {
    fetch('/contact.php', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json', // sending JSON formatted data
      },
      body: JSON.stringify(coords),
    })
.then(response => response.text())
.then(data => {
      console.log('Server response:', data);
    })
.catch(error => {
      console.error('Error sending location to server:', error);
    });
  }

function loadShops() {
    fetch('/contact.php')
    .then((response) => response.json())
    .then((shopLocation) => {
        shopLocation.forEach((shopLocation) => {
            new google.maps.Marker({
                position: {lat: parseFloat(shopLocation.latitude), lng: parseFloat(shopLocation.longitude)},
        });
        }
    )}
    )}
  /*  
  function loadShops() {
fetch('/contact.php')
  .then(response => response.json())
  .then(data => {
    const map = new google.maps.Map(document.getElementById("map"), {
      zoom: 12,
      center: { lat: data[0].latitude, lng: data[0].longitude },
    });

    data.forEach(shop => {
      new google.maps.Marker({
        position: { lat: shop.latitude, lng: shop.longitude },
        map: map,
        title: shop.name,
      });
    });
  });
}

*/