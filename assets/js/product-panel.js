jQuery(function ($) {
  function syncNavardFields() {
    const modType = $('#navard_mod_type').val();
    
    if (modType === 'percent') {
      $('.navard-mod-value-field').show();
      $('.navard-help-percent').show();
      $('.navard-help-fixed').hide();
    } else if (modType === 'fixed') {
      $('.navard-mod-value-field').show();
      $('.navard-help-percent').hide();
      $('.navard-help-fixed').show();
    } else {
      $('.navard-mod-value-field').hide();
    }

    if ($('#navard_round').is(':checked')) {
      $('.navard-round-unit-field').show();
    } else {
      $('.navard-round-unit-field').hide();
    }
  }

  $(document).on('change', '#navard_mod_type, #navard_round', syncNavardFields);
  
  $('#woocommerce-product-data').on('woocommerce_tabs_loaded', syncNavardFields);
  $('body').on('woocommerce-product-type-change', syncNavardFields);

  syncNavardFields();
});