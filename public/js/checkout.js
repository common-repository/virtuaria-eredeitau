jQuery(document).ready(function( $ ){
    $(document).on("keyup", "#erede-card-expiry", function () {
		var v = $(this).val().replace(/\D/g, "");

		v = v.replace(/(\d{2})(\d)/, "$1 / $2");

		$(this).val(v);
	});

	$(document).on("click", "#erede-use-other-card", function () {
		if ($(this).prop("checked")) {
			$("#erede-credit-card-form .form-row").removeClass("card-loaded");
			$(".card-in-use.erede").hide("fast");
			$("#erede-payment").removeClass("card-loaded");
		} else {
			$("#erede-credit-card-form .form-row").addClass("card-loaded");
			$(".card-in-use.erede").show("fast");
			$("#erede-card-installments-field").removeClass("card-loaded");
			$("#erede-payment").addClass("card-loaded");
		}
	});

	$(document).on('focusout', '#erede-card-expiry', function() {
		if ( $(this).val().length == 7 ) {
			var v = $(this).val().replace(/\D/g, "");

			let century = new Date().getFullYear().toString().substring(0, 2);
			v = v.replace(/(\d{2})(\d)/, "$1 / " + century + "$2");

			$(this).val(v);
		}
	});
});