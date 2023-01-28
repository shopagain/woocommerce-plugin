/**
 * WooCommerce ShopAgain Started Checkout
 *
 * Incoming event object
 * @typedef {object} sha_checkout
 *   @property {string} email - Email of current logged in user
 *   
 *   @property {object} event_data - Data for started checkout event
 *     @property {object} $extra - Event data
 *     @property {string} $service - Value will always be "woocommerce"
 *     @property {int} $value - Total value of checkout event
 *     @property {array} Categories - Product categories (array of strings)
 *     @property {string} Currency - Currency type
 *     @property {string} CurrencySymbol - Currency type symbol
 *     @property {array} ItemNames - List of items in the cart
 *
 */


/**
 * Attach event listeners to save billing fields.
 */

var shopagainLocalStorage = {
    // use this to get or set items in localstorage
    getObject: function () {
        const objValueResp = localStorage.getItem(shopagain_localstorage_key);
        let objValue = {};
        try {
            objValue = JSON.parse(objValueResp);
        }
        catch (e) {
            console.log(e);
        }
        if (typeof objValue !== "object" || objValue === null) {
            objValue = {};
        }
        return objValue;
    },
    set: function (key, value) {
        const objValue = this.getObject();
        objValue[key] = value;
        localStorage.setItem(shopagain_localstorage_key, JSON.stringify(objValue));
    },
    del: function (key) {
        const objValue = this.getObject();
        delete objValue[key];
        localStorage.setItem(shopagain_localstorage_key, JSON.stringify(objValue));
    },
    get: function (key) {
        const objValue = this.getObject();
        return objValue[key];
    },
}

var shopagain_identify_object = {
  'token': shopagain_public_key.token,
  'properties': {}
};

var shopagain_cookie_id = '__shopagain_id';

function shopagainMakePublicAPIcall(endpoint, event_data) {
  data_param = btoa(unescape(encodeURIComponent(JSON.stringify(event_data))));
  jQuery.get(shopagain_public_key.callback_url + endpoint + '?data=' + data_param);
}

function getShopagainCookie() {
  var name = shopagain_cookie_id + "=";
  var decodedCookie = decodeURIComponent(document.cookie);
  var ca = decodedCookie.split(';');
  for (var i = 0; i < ca.length; i++) {
    var c = ca[i];
    while (c.charAt(0) == ' ') {
      c = c.substring(1);
    }
    if (c.indexOf(name) == 0) {
      return atob(c.substring(name.length, c.length));
    }
  }
  return "";
}

function setShopagainCookie(cookie_data) {
  cvalue = btoa(JSON.stringify(cookie_data));
  var date = new Date();
  date.setTime(date.getTime() + (63072e6)); // adding 2 years in milliseconds to current time
  var expires = "expires=" + date.toUTCString();
  document.cookie = shopagain_cookie_id + "=" + cvalue + ";" + expires + "; path=/";
}

function shIdentifyBillingField() {
  var billingFields = ["first_name", "last_name"];
  for (var i = 0; i < billingFields.length; i++) {
    (function () {
      var nameType = billingFields[i];
      jQuery('input[name="billing_' + nameType + '"]').change(function () {
        var email = jQuery('input[name="billing_email"]').val();
        if (email) {
          identify_properties = {
            '$email': email,
            [nameType]: jQuery.trim(jQuery(this).val())
          };
          setShopagainCookie(identify_properties);
          shopagain_identify_object.properties = identify_properties;

          shopagainMakePublicAPIcall('identify', shopagain_identify_object);
        }
      })
    })();
  }
}

window.addEventListener("load", function () {

  if (typeof sha_checkout === 'undefined') {
    return;
  }
  var uid = shopagainLocalStorage.get("uid");
  var SHA = SHA || {};
  SHA.trackStartedCheckout = function () {
    var event_object = {
      'token': shopagain_public_key.token,
      'event': '$started_checkout',
      'customer_properties': {},
      'properties': sha_checkout.event_data
    };

    if (sha_checkout.email || uid) {
      event_object.customer_properties['$email'] = sha_checkout.email;
      event_object.customer_properties['uid'] = uid;
    } else {
      return;
    }

    shopagainMakePublicAPIcall('track', event_object);
  };

  var klCookie = getShopagainCookie();
  if (sha_checkout.email !== "") {
    shopagain_identify_object.properties = {
      '$email': sha_checkout.email
    };
    shopagainMakePublicAPIcall('identify', shopagain_identify_object);
    setShopagainCookie(shopagain_identify_object.properties);
    SHA.trackStartedCheckout();
  } else if (uid) {
    SHA.trackStartedCheckout();
  } else if (klCookie && JSON.parse(klCookie).$email !== undefined) {
    sha_checkout.email = JSON.parse(klCookie).$email;
    SHA.trackStartedCheckout();
  } else {
    if (jQuery) {
      jQuery('input[name="billing_email"]').change(function () {
        var elem = jQuery(this),
          email = jQuery.trim(elem.val());

        if (email && /@/.test(email)) {
          var params = {
            "$email": email
          };
          var first_name = jQuery('input[name="billing_first_name"]').val();
          var last_name = jQuery('input[name="billing_last_name"]').val();
          if (first_name) {
            params["$first_name"] = first_name;
          }
          if (last_name) {
            params["$last_name"] = last_name;
          }

          setShopagainCookie(params);
          sha_checkout.email = params.$email;
          shopagain_identify_object.properties = params;
          shopagainMakePublicAPIcall('identify', shopagain_identify_object);
          SHA.trackStartedCheckout();
        }
      });
      shIdentifyBillingField();
    }
  }
});
