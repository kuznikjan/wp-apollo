
  function notPaidWarn(url) {
    var r = confirm("There is no payment method for order. Invoice should be created AFTER payment. Are you sure you want to create invoice?");
    if (r == true) {
      window.location.replace(url);
    }
  };
