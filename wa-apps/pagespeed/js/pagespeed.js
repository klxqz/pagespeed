(function ($) {
// js controller
    $.pagespeed = {
// init js controller
        init: function () {
// if history exists

            if (typeof ($.History) != "undefined") {
                $.History.bind(function (hash) {
                    $.pagespeed.dispatch(hash);
                });
            }
            var hash = window.location.hash;
            if (hash === '#/' || !hash) {
                this.dispatch();
            } else {
                $.wa.setHash(hash);
            }
        },
        // dispatch call method by hash
        dispatch: function (hash) {
            if (hash === undefined) {
                hash = location.hash.replace(/^[^#]*#\/*/, '');
            }
            if (hash) {
                // clear hash
                hash = hash.replace(/^.*#/, '');
                hash = hash.split('/');
                if (hash[0]) {
                    var actionName = "";
                    var attrMarker = hash.length;
                    for (var i = 0; i < hash.length; i++) {
                        var h = hash[i];
                        if (i < 2) {
                            if (i === 0) {
                                actionName = h;
                            } else if (parseInt(h, 10) != h) {
                                actionName += h.substr(0, 1).toUpperCase() + h.substr(1);
                            } else {
                                attrMarker = i;
                                break;
                            }
                        } else {
                            attrMarker = i;
                            break;
                        }
                    }
                    var attr = hash.slice(attrMarker);
                    // call action if it exists
                    if (this[actionName + 'Action']) {
                        this.currentAction = actionName;
                        this.currentActionAttr = attr;
                        this[actionName + 'Action'](attr);
                    } else {
                        if (console) {
                            console.log('Invalid action name:', actionName + 'Action');
                        }
                    }
                } else {
                    // call default action
                    this.defaultAction();
                }
            } else {
                // call default action
                this.defaultAction();
            }
        },
        defaultAction: function () {
            window.location.href = '#/settings/';
        },
        settingsAction: function (id) {
            var self = this;
            $("#content").load('?module=settings', function () {
                self.initSettingsHandlers();
            });
        },
        initSettingsHandlers: function () {
            $('#ibutton-status').iButton({
                labelOn: "Вкл", labelOff: "Выкл"
            }).change(function () {
                var self = $(this);
                var enabled = self.is(':checked');
                if (enabled) {
                    self.closest('.field-group').siblings().show(200);
                } else {
                    self.closest('.field-group').siblings().hide(200);
                }
                var f = $("#settings-form");
                $.post(f.attr('action'), f.serialize());
            });

            $('[name="settings[css_download_remote_files]"]').change(function () {
                $('.css-update-time-remote-files').slideToggle();
            });
            $('[name="settings[css_gzip]"]').change(function () {
                $('.css-gzip-level').slideToggle();
            });

            $('#content .ibutton').iButton({
                labelOn: "Вкл",
                labelOff: "Выкл",
                className: 'mini'
            });

            $('#settings-form').submit(function () {
                var form = this;
                $.ajax({
                    type: 'POST',
                    url: $(form).attr('action'),
                    dataType: 'json',
                    data: $(form).serialize(),
                    success: function (data, textStatus, jqXHR) {
                        if (data.status == 'ok') {
                            $(form).find('.value.submit .response').html(data.data.message);
                        } else {
                            $(form).find('.value.submit .response').html(data.errors.join(' '));
                        }
                        setTimeout(function () {
                            $(form).find('.value.submit .response').empty();
                        }, 5000);
                    },
                    error: function (jqXHR) {
                        alert(jqXHR.responseText);
                    }
                });
                return false;
            });
        }
    }
})(jQuery);