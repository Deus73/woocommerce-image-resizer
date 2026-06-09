(function ($) {
    'use strict';

    function setProgress(percent, message) {
        $('.wpir-progress').prop('hidden', false);
        $('.wpir-progress-bar span').css('width', percent + '%');
        $('.wpir-status').text(message);
    }

    function getErrorMessage(response, xhr) {
        if (response && response.data && response.data.message) {
            return response.data.message;
        }

        if (xhr && xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) {
            return xhr.responseJSON.data.message;
        }

        if (xhr && xhr.responseText) {
            return xhr.responseText.replace(/<[^>]*>/g, ' ').replace(/\s+/g, ' ').trim().substring(0, 300);
        }

        return wpirAdmin.i18n.failed;
    }

    function runBatch(page, totals) {
        var categoryId = parseInt($('#wpir-product-category').val(), 10) || 0;
        var categoryLabel = categoryId ? $('#wpir-product-category option:selected').text() : 'alle categorieen';

        $.ajax({
            url: wpirAdmin.ajaxUrl,
            method: 'POST',
            dataType: 'json',
            data: {
                action: 'wpir_batch_resize',
                nonce: wpirAdmin.nonce,
                page: page,
                categoryId: categoryId
            }
        }).done(function (response) {
            if (!response || !response.success) {
                setProgress(100, wpirAdmin.i18n.failed + ' ' + getErrorMessage(response));
                $('#wpir-start-batch').prop('disabled', false);
                $('#wpir-product-category').prop('disabled', false);
                return;
            }

            var data = response.data;
            totals.processed += data.processed;
            totals.skipped += data.skipped;
            totals.errors += data.errors.length;

            var percent = data.totalPages > 0 ? Math.min(100, Math.round((data.page / data.totalPages) * 100)) : 100;
            setProgress(percent, 'Categorie: ' + categoryLabel + ' · pagina ' + data.page + ' van ' + data.totalPages + ' · verwerkt: ' + totals.processed + ' · overgeslagen: ' + totals.skipped + ' · fouten: ' + totals.errors);

            if (data.done) {
                var message = wpirAdmin.i18n.done + ' Verwerkt: ' + totals.processed + ', overgeslagen: ' + totals.skipped + ', fouten: ' + totals.errors + '.';
                if (data.errors.length) {
                    message += ' Laatste fout: afbeelding ' + data.errors[0].id + ': ' + data.errors[0].message;
                }
                setProgress(100, message);
                $('#wpir-start-batch').prop('disabled', false);
                $('#wpir-product-category').prop('disabled', false);
                return;
            }

            runBatch(data.nextPage, totals);
        }).fail(function (xhr) {
            setProgress(100, wpirAdmin.i18n.failed + ' ' + getErrorMessage(null, xhr));
            $('#wpir-start-batch').prop('disabled', false);
            $('#wpir-product-category').prop('disabled', false);
        });
    }

    $(function () {
        $('#wpir-start-batch').on('click', function () {
            $(this).prop('disabled', true);
            $('#wpir-product-category').prop('disabled', true);
            setProgress(0, wpirAdmin.i18n.running);
            runBatch(1, { processed: 0, skipped: 0, errors: 0 });
        });
    });
})(jQuery);
