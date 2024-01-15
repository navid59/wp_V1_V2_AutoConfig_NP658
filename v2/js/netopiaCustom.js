var ntpNotify = netopiaUIPath_data.ntp_notify;
var ntpShopConfig = "https://shop-config-fqvtst6pfa-ew.a.run.app";

document.addEventListener('DOMContentLoaded', function() {
    var popupLink = document.getElementById('woocommerce_netopiapayments_wizard_button');
    document.getElementById('woocommerce_netopiapayments_ntp_notify').innerHTML= ntpNotify;
    popupLink.addEventListener('click', function(e) {
        // var netopiaUIPath_dataPluginUrl = netopiaUIPath_data.plugin_url;
        var netopiaUIPath_dataSiteUrl = netopiaUIPath_data.site_url;
        var pluginCallback = netopiaUIPath_dataSiteUrl+'/index.php/wp-json/netopiapayments/v1/updatecredential/';
        var pluginSecKey = netopiaUIPath_data.sKey;
        

      e.preventDefault();
      // openPopupWindow(ntpShopConfig+'?callback='+pluginCallback, 'Popup Form', 700, 700); // to have popup
      redirect(ntpShopConfig, {callback : pluginCallback, seckey:pluginSecKey}); // to redirects
    });
  });
  
  function openPopupWindow(url, title, width, height) {
    var left = (window.innerWidth - width) / 2;
    var top = (window.innerHeight - height) / 2;
    var options = 'toolbar=no, location=no, directories=no, status=no, menubar=no, scrollbars=no, resizable=no, copyhistory=no, width=' + width + ', height=' + height + ', top=' + top + ', left=' + left;
    window.open(url, title, options);
  }

  function redirect(url, params) {
    // Create a form element
    var form = document.createElement('form');
    form.method = 'POST'; // Set the HTTP method to POST
    form.action = url;   // Set the form action to the provided URL

    // Create hidden input fields for each parameter
    for (var key in params) {
        if (params.hasOwnProperty(key)) {
            var input = document.createElement('input');
            input.type = 'hidden';
            input.name = key;
            input.value = params[key];
            form.appendChild(input);
        }
    }

    // Append the form to the document body and submit it
    document.body.appendChild(form);
    form.submit();
}

  // Function to handle the popup window close event
  function handlePopupWindowClose() {    
    // Reload the parent window (NETOPIA Payments admin page)
    location.reload();
  }