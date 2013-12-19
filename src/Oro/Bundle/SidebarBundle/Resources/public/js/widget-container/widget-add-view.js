/*jslint nomen: true, vars: true*/
/*global define, requirejs*/

define(function (require) {
    'use strict';

    var $ = require('jquery');
    var _ = require('underscore');

    var widgetAddTemplate = require('text!oro/sidebar/widget-container/widget-add-template');
    var WidgetContainerModel = require('oro/sidebar/widget-container/model');

    var Modal = require('oro/modal');
    var DialogWidget = require('oro/dialog-widget');
    var constants = require('oro/sidebar/constants');

    /**
     * @export  oro/sidebar/widget-controller/widget-add-view
     * @class oro.sidebar.widget-controller.WidgetAddView
     * @extends oro.Modal
     */
    var WidgetAddView = Modal.extend({
        /** @property {String} */
        className: 'modal oro-modal-normal',

        initialize: function (options) {
            var view = this;
            var model = view.model;

            options.content = _.template(widgetAddTemplate, model.toJSON());

            Modal.prototype.initialize.apply(this, arguments);
        },

        open: function () {
            var view = this;
            var model = view.model;

            Modal.prototype.open.apply(this, arguments);

            var selected = null;

            view.$el.find('ol').selectable({
                selected: function (event, ui) {
                    selected = ui.selected;
                }
            });

            view.once('ok', function () {
                if (!selected) {
                    view.close();
                    return;
                }

                var widgetName = $(selected).closest('li').data('widgetname');
                var widgetData = model.get('availableWidgets')[widgetName];

                var placement = null;
                if (model.get('position') === constants.SIDEBAR_LEFT) {
                    placement = 'left';
                } else if (model.get('position') === constants.SIDEBAR_RIGHT) {
                    placement = 'right';
                }

                var widget = new WidgetContainerModel({
                    widgetName: widgetName,
                    position: model.widgets.length,
                    placement: placement
                });
                widget.update(widgetData);
                widget.set('settings', widgetData.settings);

                model.widgets.push(widget);

                widget.save();

                view.close();
            });
        }
    });

    return WidgetAddView;
});
