(function ($) {
    $.images = {
        form: null,
        ajax_pull: {},
        progress: false,
        id: null,
        debug: {
            'memory': 0.0,
            'memory_avg': 0.0
        },
        data: {
            'params': {}
        },
        $form: null,
        init: function (data) {
            this.$form = $("#s-plugin-imgimport-import");
            $.extend(this.data, data);
            this.initForm();
        },
        initForm: function () {
            var self = this;
            this.$form.unbind('submit.imgimport').bind('submit.imgimport', function (event) {

                var $form = $(this);
                $form.find(':input, :submit').attr('disabled', false);
                self.imgimportHandler(this);

                return false;
            });


        },
        onInit: function () {
            this.initForm();
        },
        imgimportHandler: function (element) {
            this.form = $(element);
            /**
             * reset required form fields errors
             */
            this.form.find('.value.js-required :input.error').removeClass('error');
            /**
             * verify form
             */
            var valid = true;
            this.form.find('.value.js-required :input:visible:not(:disabled)').each(function () {
                var $this = $(this);
                var value = $this.val();
                if (!value || (value == 'skip:')) {
                    $this.addClass('error');
                    valid = false;
                }
            });
            if (!valid) {
                var $target = this.form.find('.value.js-required :input.error:first');

                $('html, body').animate({
                    scrollTop: $target.offset().top - 10
                }, 1000, function () {
                    $target.focus();
                });
                this.form.find(':input, :submit').attr('disabled', null);
                return false;
            }

            this.progress = true;

            var data = this.form.serialize();
            this.form.find('.errormsg').text('');
            this.form.find(':input').attr('disabled', true);
            this.form.find('a.js-action:visible').data('visible', 1).hide();
            this.form.find(':submit').hide();
            this.form.find('#plugin-imgimport-submit .progressbar .progressbar-inner').css('width', '0%');
            this.form.find('#plugin-imgimport-submit .progressbar').show();
            var url = $(element).attr('action');
            var self = this;
            $.ajax({
                url: url,
                data: data,
                dataType: 'json',
                type: 'post',
                success: function (response) {
                    console.log(response);
                    if (response.error) {
                        self.form.find(':input').attr('disabled', false);
                        self.form.find(':submit').show();
                        self.form.find('a.js-action:hidden').each(function () {
                            var $this = $(this);
                            if ($this.data('visible')) {
                                $this.show();
                                $this.data('visible', null);
                            }
                        });
                        self.form.find('#plugin-imgimport-submit .js-progressbar-container').hide();
                        self.form.find('.shop-ajax-status-loading').remove();
                        self.progress = false;
                        self.form.find('.errormsg').text(response.error);
                    } else {
                        self.form.find('#plugin-imgimport-submit .progressbar').attr('title', '0.00%');
                        self.form.find('#plugin-imgimport-submit .progressbar-description').text('0.00%');
                        self.form.find('#plugin-imgimport-submit .js-progressbar-container').show();

                        self.ajax_pull[response.processId] = [];
                        self.ajax_pull[response.processId].push(setTimeout(function () {
                            $.wa.errorHandler = function (xhr) {
                                return !((xhr.status >= 500) || (xhr.status == 0));
                            };
                            self.progressHandler(url, response.processId, response);
                        }, 2100));
                        self.ajax_pull[response.processId].push(setTimeout(function () {
                            self.progressHandler(url, response.processId, null);
                        }, 5500));
                    }
                },
                error: function (response) {
                    if (response.responseText) {
                        self.form.find('#plugin-imgimport-submit .errormsg').html(response.responseText);
                    } else {
                        self.form.find('#plugin-imgimport-submit .errormsg').html('Ошибка: ' + response.status);
                    }
                    self.form.find(':input').attr('disabled', false);
                    self.form.find('a.js-action:hidden').each(function () {
                        var $this = $(this);
                        if ($this.data('visible')) {
                            $this.show();
                            $this.data('visible', null);
                        }
                    });
                    self.form.find(':submit').show();
                    self.form.find('#plugin-imgimport-submit .js-progressbar-container').hide();
                    self.form.find('#plugin-imgimport-submit .shop-ajax-status-loading').remove();
                    self.form.find('#plugin-imgimport-submit .progressbar').hide();
                }
            });
            return false;
        },
        onDone: function (url, processId, response) {

        },
        progressHandler: function (url, processId, response) {
            // display progress
            // if not completed do next iteration
            var self = this;
            var $bar;
            if (response && response.ready) {
                $.wa.errorHandler = null;
                var timer;
                while (timer = self.ajax_pull[processId].pop()) {
                    if (timer) {
                        clearTimeout(timer);
                    }
                }
                $bar = self.form.find('#plugin-imgimport-submit .progressbar .progressbar-inner');
                $bar.css({
                    'width': '100%'
                });
                $.shop.trace('cleanup', response.processId);


                $.ajax({
                    url: url,
                    data: {
                        'processId': response.processId,
                        'cleanup': 1
                    },
                    dataType: 'json',
                    type: 'post',
                    success: function (response) {
                        console.log(response);
                        $.shop.trace('report', response);
                        $("#plugin-imgimport-submit").hide();
                        self.form.find('#plugin-imgimport-submit .progressbar').hide();
                        var $report = $("#plugin-imgimport-report");
                        $report.show();
                        if (response.report) {
                            $report.html(response.report);
                        }
                        $.storage.del('shop/hash');
                    }, error: function (response) {
                        console.log(response);
                    }
                });

            } else if (response && response.error) {

                self.form.find(':input').attr('disabled', false);
                self.form.find(':submit').show();
                self.form.find('#plugin-imgimport-submit .js-progressbar-container').hide();
                self.form.find('.shop-ajax-status-loading').remove();
                self.form.find('#plugin-imgimport-submit .progressbar').hide();
                self.form.find('.errormsg').text(response.error);

            } else {
                var $description;
                if (response && (typeof (response.progress) != 'undefined')) {
                    $bar = self.form.find('#plugin-imgimport-submit .progressbar .progressbar-inner');
                    var progress = parseFloat(response.progress.replace(/,/, '.'));
                    $bar.animate({
                        'width': progress + '%'
                    });
                    self.debug.memory = Math.max(0.0, self.debug.memory, parseFloat(response.memory) || 0);
                    self.debug.memory_avg = Math.max(0.0, self.debug.memory_avg, parseFloat(response.memory_avg) || 0);

                    var title = 'Memory usage: ' + self.debug.memory_avg + '/' + self.debug.memory + 'MB';
                    title += ' (' + (1 + response.stage_num) + '/' + (parseInt(response.stage_count)) + ')';

                    var message = response.progress + ' — ' + response.stage_name;

                    $bar.parents('.progressbar').attr('title', response.progress);
                    $description = self.form.find('#plugin-imgimport-submit .progressbar-description');
                    $description.text(message);
                    $description.attr('title', title);
                }
                if (response && (typeof (response.warning) != 'undefined')) {
                    $description = self.form.find('#plugin-imgimport-submit .progressbar-description');
                    $description.append('<i class="icon16 exclamation"></i><p>' + response.warning + '</p>');
                }

                var ajax_url = url;
                var id = processId;

                self.ajax_pull[id].push(setTimeout(function () {
                    $.ajax({
                        url: ajax_url,
                        data: {
                            'processId': id
                        },
                        dataType: 'json',
                        type: 'post',
                        success: function (response) {
                            self.progressHandler(url, response ? response.processId || id : id, response);
                        },
                        error: function (response) {
                            var $description;
                            $description = self.form.find('#plugin-imgimport-submit .progressbar-description');
                            $description.append('<i class="icon16 exclamation"></i>' + response.responseText);
                            self.progressHandler(url, id, null);
                        }
                    });
                }, 2000));
            }
        },
        getLink: function () {
            window.location.reload();

        }
    };
})(jQuery); 