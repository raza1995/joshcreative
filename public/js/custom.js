(function ($) {
    "use strict";

    // ✅ Global Toast Function
    function showToast(type, message) {
        Swal.fire({
            toast: true,
            position: 'top-end',
            icon: type, // 'success', 'error', 'warning', 'info'
            title: message,
            showConfirmButton: false,
            timer: 3000,
            timerProgressBar: true
        });
    }

    // ✅ Make showToast globally accessible
    window.showToast = showToast;

    $(document).ready(function () {
        const shopifyOrderSelect = $('#shopify_order_id');
        const ajaxUrl = shopifyOrderSelect.data('url'); // ✅ Get URL from data attribute

        shopifyOrderSelect.select2({
            placeholder: 'Search by Customer Name, Email, or Order Number...',
            ajax: {
                url: ajaxUrl, // ✅ Use the dynamic URL
                dataType: 'json',
                delay: 250,
                data: function (params) {
                    return {
                        q: params.term // Search term
                    };
                },
                processResults: function (data) {
                    return {
                        results: data.map(function (order) {
                            return {
                                id: order.id,
                                text: `${order.order_number} - ${order.customer_name} (${order.email_address})`
                            };
                        })
                    };
                },
                cache: true
            },
            minimumInputLength: 1
        });
    });
})(jQuery);
