/**
 * DevBrothers Cyrillic URL Admin Scripts
 *
 * @package DevBrothers_Cyrillic_Slugs
 */

(function($) {
    'use strict';

    var i18n = typeof dbcsData !== 'undefined' ? dbcsData : {};

    $(document).ready(function() {

        var conversionInProgress = false;
        var totalConverted = 0;
        var offset = 0;

        $('#dbcs-convert-button').on('click', function(e) {
            e.preventDefault();

            if (conversionInProgress) {
                return;
            }

            if (!confirm(i18n.confirm_convert || 'Are you sure you want to convert all existing URLs?')) {
                return;
            }

            startConversion();
        });

        function startConversion() {
            conversionInProgress = true;
            totalConverted = 0;
            offset = 0;

            $('#dbcs-convert-button')
                .prop('disabled', true)
                .find('.dashicons')
                .addClass('dbcs-spinning');

            $('#dbcs-conversion-progress').slideDown();
            $('#dbcs-conversion-result').slideUp();

            convertBatch();
        }

        function convertBatch() {
            $.ajax({
                url: dbcsData.ajax_url,
                type: 'POST',
                data: {
                    action: 'dbcs_convert_urls',
                    nonce: dbcsData.nonce,
                    offset: offset
                },
                success: function(response) {
                    if (response.success) {
                        var data = response.data;

                        totalConverted += data.converted_posts + data.converted_terms;

                        var progress = data.total > 0
                            ? Math.min(100, Math.round((totalConverted / data.total) * 100))
                            : 100;

                        if (!data.has_more) {
                            progress = 100;
                        }

                        updateProgress(progress, totalConverted, data.total);

                        if (data.has_more) {
                            offset = data.offset;
                            convertBatch();
                        } else {
                            finishConversion(totalConverted, data.errors);
                        }
                    } else {
                        showError(response.data.message || i18n.unknown_error || 'An unknown error occurred');
                    }
                },
                error: function(xhr, status, error) {
                    var ajaxErrorText = (i18n.ajax_error || 'AJAX Error: {error}').replace('{error}', error);
                    showError(ajaxErrorText);
                }
            });
        }

        function updateProgress(percent, converted, total) {
            $('#dbcs-progress-bar').css('width', percent + '%');
            var progressText = i18n.progress_text || 'Processed: {converted} of {total} ({percent}%)';
            progressText = progressText
                .replace('{converted}', converted)
                .replace('{total}', total)
                .replace('{percent}', percent);
            $('#dbcs-progress-text').text(progressText);
        }

        function finishConversion(converted, errors) {
            conversionInProgress = false;

            $('#dbcs-convert-button')
                .prop('disabled', false)
                .find('.dashicons')
                .removeClass('dbcs-spinning');

            $('#dbcs-conversion-progress').slideUp();

            var completedTitle = i18n.conversion_completed || 'Conversion completed!';
            var convertedText = (i18n.converted_count || 'Successfully converted: {count} items.')
                .replace('{count}', converted);

            var resultHtml = '<div class="notice notice-success">';
            resultHtml += '<p><strong>' + escapeHtml(completedTitle) + '</strong></p>';
            resultHtml += '<p>' + escapeHtml(convertedText) + '</p>';

            if (errors && errors.length > 0) {
                var errorsText = (i18n.errors_count || 'Errors: {count}').replace('{count}', errors.length);
                var showErrorsText = i18n.show_errors || 'Show errors';
                resultHtml += '<p class="dbcs-error-text">' + escapeHtml(errorsText) + '</p>';
                resultHtml += '<details class="dbcs-error-details"><summary>' + escapeHtml(showErrorsText) + '</summary>';
                resultHtml += '<ul class="dbcs-errors-list">';
                errors.forEach(function(error) {
                    resultHtml += '<li>' + escapeHtml(error) + '</li>';
                });
                resultHtml += '</ul></details>';
            }

            resultHtml += '</div>';

            $('#dbcs-conversion-result').html(resultHtml).slideDown();

            $('html, body').animate({
                scrollTop: $('#dbcs-conversion-result').offset().top - 100
            }, 500);
        }

        function showError(message) {
            conversionInProgress = false;

            $('#dbcs-convert-button')
                .prop('disabled', false)
                .find('.dashicons')
                .removeClass('dbcs-spinning');

            $('#dbcs-conversion-progress').slideUp();

            var errorTitle = i18n.error_title || 'Error!';
            var errorHtml = '<div class="notice notice-error">';
            errorHtml += '<p><strong>' + escapeHtml(errorTitle) + '</strong></p>';
            errorHtml += '<p>' + escapeHtml(message) + '</p>';
            errorHtml += '</div>';

            $('#dbcs-conversion-result').html(errorHtml).slideDown();
        }

        function escapeHtml(text) {
            var map = {
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#039;'
            };
            return text.replace(/[&<>"']/g, function(m) { return map[m]; });
        }

        $(window).on('beforeunload', function() {
            if (conversionInProgress) {
                return i18n.leave_page_warning || 'Conversion is not yet complete. Are you sure you want to leave?';
            }
        });

    });

})(jQuery);
