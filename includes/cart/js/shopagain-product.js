/**
 * ShopAgain Viewed Product
 *
 * Incoming product object
 * @typedef {Object} shopagain_item
 *   @property {string} title - Product name
 *   @property {int} product_id - Parent product ID
 *   @property {int} variant_id - Product ID
 *   @property {string} url - Product permalink
 *   @property {string} image_url - Product image url
 *   @property {float} price - Product price
 *   @property {array} categories - Product categories (array of strings)
 *
 * Unfortunately wp_localize_script converts all variables to strings :( so we
 * will have to re-parse ints and floats.
 * See note in - https://codex.wordpress.org/Function_Reference/wp_localize_script
 *
 */

var _shopagainq = _shopagainq || [];

var shopagain_item = {
    'Title': shopagain_item.title,
    'ItemId': parseInt(shopagain_item.product_id),
    'variantId': parseInt(shopagain_item.variant_id),
    'Categories': shopagain_item.categories,
    'ImageUrl': shopagain_item.image_url,
    'Url': shopagain_item.url,
    'Metadata': {
        'Price': parseFloat(shopagain_item.price),
    }
};

_shopagainq.push(['track', 'Viewed Product', shopagain_item]);
_shopagainq.push(['trackViewedItem', shopagain_item]);
