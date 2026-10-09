/**
 * Content Intelligence - Control Panel JavaScript
 */

(function($) {
    /**
     * Universal copy to clipboard helper that works seamlessly across:
     * - Secure contexts (HTTPS and localhost)
     * - Insecure contexts (HTTP custom domains like http://local.craftlearning.com)
     * - Mobile, iframes, and legacy browsers
     */
    window.ciCopyToClipboard = function(text, $btn) {
        if (!text) {
            return;
        }

        function notifySuccess() {
            if ($btn && $btn.length) {
                var originalText = $btn.data('ci-orig-text') || $btn.text();
                $btn.data('ci-orig-text', originalText);
                $btn.text(Craft.t('content-intelligence', 'Copied!'));
                $btn.addClass('success');
                setTimeout(function() {
                    $btn.text(originalText);
                    $btn.removeClass('success');
                }, 1500);
            }
            Craft.cp.displayNotice(Craft.t('content-intelligence', 'Copied to clipboard!'));
        }

        function fallbackCopy() {
            try {
                var textArea = document.createElement("textarea");
                textArea.value = text;
                textArea.setAttribute('readonly', '');
                textArea.style.position = 'fixed';
                textArea.style.left = '-9999px';
                textArea.style.top = '0';
                textArea.style.opacity = '0';
                document.body.appendChild(textArea);
                textArea.focus();
                textArea.select();
                var successful = document.execCommand('copy');
                document.body.removeChild(textArea);
                if (successful) {
                    notifySuccess();
                } else {
                    window.prompt(Craft.t('content-intelligence', 'Copy to clipboard: Ctrl+C, Enter'), text);
                }
            } catch (err) {
                window.prompt(Craft.t('content-intelligence', 'Copy to clipboard: Ctrl+C, Enter'), text);
            }
        }

        // Try Async Clipboard API if available and context is secure
        if (navigator.clipboard && window.isSecureContext) {
            navigator.clipboard.writeText(text).then(function() {
                notifySuccess();
            }).catch(function() {
                fallbackCopy();
            });
        } else {
            fallbackCopy();
        }
    };

    if (typeof window.ContentIntelligence === 'undefined') {
        window.ContentIntelligence = {
            init: function() {
                this.bindActions();
            },

            bindActions: function() {
                // Global copy button handler for target elements
                $(document).on('click', '.ci-copy-btn', function(e) {
                    e.preventDefault();
                    var target = $(this).data('target');
                    var text = $(target).is('input, textarea') ? $(target).val() : $(target).text();
                    window.ciCopyToClipboard(text, $(this));
                });

                // Global copy button handler for direct value attributes
                $(document).on('click', '.ci-copy-val', function(e) {
                    e.preventDefault();
                    var val = $(this).data('val') || $(this).attr('data-val') || $(this).closest('div').parent().find('strong').text();
                    window.ciCopyToClipboard(val, $(this));
                });

                $('[data-ci-action]').on('click', function(e) {
                    var action = $(this).data('ci-action');
                    if (action === 'refresh') {
                        location.reload();
                    }
                });
            }
        };

        $(document).ready(function() {
            window.ContentIntelligence.init();
        });
    }
})(jQuery);
