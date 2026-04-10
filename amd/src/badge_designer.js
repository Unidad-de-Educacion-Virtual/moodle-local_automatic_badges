// This file is part of local_automatic_badges - https://moodle.org/.
//
// local_automatic_badges is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// local_automatic_badges is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with local_automatic_badges.  If not, see <https://www.gnu.org/licenses/>.

/**
 * AMD module for the badge designer editor.
 *
 * Fabric.js and Sortable.js are loaded as global scripts in the page <head>
 * via $PAGE->requires->js() before RequireJS initialises, making them available
 * as window.fabric and window.Sortable when this module runs.
 *
 * @module     local_automatic_badges/badge_designer
 * @copyright  2026 Daniela Alexandra Patiño Dávila, Cristian Julian Lamus Lamus
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define(['jquery', 'core/ajax'], function($, Ajax) {
    'use strict';

    /**
     * Initialise the badge designer canvas editor.
     *
     * @param {number} courseid The course ID.
     */
    var init = function(courseid) {
        // fabric.js and Sortable.js are loaded as globals in the page head.
        var fabric = window.fabric;
        var Sortable = window.Sortable;

        if (typeof fabric === 'undefined') {
            window.console.error('Fabric.js not loaded');
            return;
        }

        var canvas = new fabric.Canvas('c', {
            backgroundColor: 'transparent',
            preserveObjectStacking: true
        });

        var centerX = 200;
        var centerY = 200;
        var baseSize = 140;
        var bgShape, iconObj, textObj;
        var activeDecos = {};

        // LAYERS PANEL.
        var updateLayersPanel = function() {
            var list = $('#layers_list');
            list.empty();

            var objects = canvas.getObjects();
            for (var i = objects.length - 1; i >= 0; i--) {
                var obj = objects[i];
                if (obj.name) {
                    var isVisible = obj.visible !== false;
                    var eyeIcon = isVisible ? 'fa-eye' : 'fa-eye-slash';
                    var eyeColor = isVisible ? 'text-primary' : 'text-muted';
                    var li = $('<li class="list-group-item layer-item d-flex justify-content-between ' +
                        'align-items-center mb-1 bg-white" style="padding: 6px 10px;" data-index="' + i + '">' +
                        '<span><i class="fa fa-grip-lines text-muted mr-2" style="cursor:grab"></i> ' +
                        obj.name + '</span>' +
                        '<span class="layer-actions">' +
                        '<button type="button" class="btn btn-sm btn-link p-0 mx-1 layer-vis-btn ' +
                        eyeColor + '" data-index="' + i + '" title="Visibilidad">' +
                        '<i class="fa ' + eyeIcon + '"></i></button>' +
                        '<button type="button" class="btn btn-sm btn-link p-0 mx-1 text-danger layer-del-btn"' +
                        ' data-index="' + i + '" title="Eliminar"><i class="fa fa-trash-alt"></i></button>' +
                        '</span></li>');
                    list.append(li);
                }
            }

            // Visibility toggle.
            $('.layer-vis-btn').off('click').on('click', function(e) {
                e.stopPropagation();
                var idx = parseInt($(this).data('index'));
                var visObj = canvas.getObjects()[idx];
                if (visObj) {
                    visObj.visible = !visObj.visible;
                    canvas.renderAll();
                    updateLayersPanel();
                }
            });

            // Delete layer.
            $('.layer-del-btn').off('click').on('click', function(e) {
                e.stopPropagation();
                var idx = parseInt($(this).data('index'));
                var delObj = canvas.getObjects()[idx];
                if (delObj) {
                    removeTrackedObject(delObj);
                    canvas.remove(delObj);
                    canvas.renderAll();
                    updateLayersPanel();
                }
            });
        };

        // Sortable for layers.
        if (document.getElementById('layers_list') && typeof Sortable !== 'undefined') {
            new Sortable(document.getElementById('layers_list'), {
                animation: 150,
                ghostClass: 'bg-light',
                onEnd: function() {
                    var items = $('#layers_list li');
                    var orderedIndices = [];
                    items.each(function() {
                        orderedIndices.push(parseInt($(this).attr('data-index')));
                    });
                    var originalObjects = canvas.getObjects().slice();
                    for (var i = orderedIndices.length - 1; i >= 0; i--) {
                        var oldIndex = orderedIndices[i];
                        var obj = originalObjects[oldIndex];
                        if (obj) {
                            obj.bringToFront();
                        }
                    }
                    canvas.renderAll();
                    updateLayersPanel();
                }
            });
        }

        var removeTrackedObject = function(obj) {
            if (obj === bgShape) {
                bgShape = null;
            }
            if (obj === iconObj) {
                iconObj = null;
            }
            if (obj === textObj) {
                textObj = null;
            }
            for (var key in activeDecos) {
                if (activeDecos[key] === obj) {
                    $('.deco-btn[data-deco="' + key + '"]').removeClass('btn-primary').addClass('btn-outline-primary');
                    delete activeDecos[key];
                }
            }
        };

        // SHAPES.
        var createShape = function(type) {
            var color = $('#badge_color_bg').val();
            var stroke = $('#badge_color_border').val();
            var strokeW = parseInt($('#badge_border_width').val()) || 8;
            var opacityVal = parseInt($('#badge_opacity').val()) / 100;
            var oldIndex = -1;

            if (bgShape) {
                oldIndex = canvas.getObjects().indexOf(bgShape);
                canvas.remove(bgShape);
            }

            var shape;
            var commonProps = {
                fill: color,
                stroke: stroke,
                strokeWidth: strokeW,
                originX: 'center',
                originY: 'center',
                left: centerX,
                top: centerY,
                selectable: true,
                opacity: opacityVal
            };

            if (type === 'circle') {
                shape = new fabric.Circle(Object.assign({radius: baseSize}, commonProps));
            } else if (type === 'square') {
                shape = new fabric.Rect(Object.assign({width: baseSize * 2, height: baseSize * 2, rx: 30, ry: 30},
                    commonProps));
            } else if (type === 'hexagon') {
                var hexPoints = [];
                for (var hi = 0; hi < 6; hi++) {
                    hexPoints.push({
                        x: baseSize * Math.cos(hi * Math.PI / 3),
                        y: baseSize * Math.sin(hi * Math.PI / 3)
                    });
                }
                shape = new fabric.Polygon(hexPoints, Object.assign({}, commonProps));
            } else if (type === 'shield') {
                var shieldPoints = [
                    {x: -baseSize * 0.9, y: -baseSize * 0.9},
                    {x: baseSize * 0.9, y: -baseSize * 0.9},
                    {x: baseSize * 0.9, y: baseSize * 0.2},
                    {x: 0, y: baseSize * 1.1},
                    {x: -baseSize * 0.9, y: baseSize * 0.2}
                ];
                shape = new fabric.Polygon(shieldPoints, Object.assign({}, commonProps));
            } else if (type === 'star') {
                var starPoints = [];
                var outerR = baseSize;
                var innerR = baseSize * 0.45;
                for (var si = 0; si < 10; si++) {
                    var sr = si % 2 === 0 ? outerR : innerR;
                    var sa = (si * 36 - 90) * Math.PI / 180;
                    starPoints.push({x: sr * Math.cos(sa), y: sr * Math.sin(sa)});
                }
                shape = new fabric.Polygon(starPoints, Object.assign({}, commonProps));
            } else if (type === 'diamond') {
                var diamondPoints = [
                    {x: 0, y: -baseSize * 1.1},
                    {x: baseSize * 0.75, y: 0},
                    {x: 0, y: baseSize * 1.1},
                    {x: -baseSize * 0.75, y: 0}
                ];
                shape = new fabric.Polygon(diamondPoints, Object.assign({}, commonProps));
            } else if (type === 'oval') {
                shape = new fabric.Ellipse(Object.assign({rx: baseSize, ry: baseSize * 0.72}, commonProps));
            } else if (type === 'pentagon') {
                var pentPoints = [];
                for (var pi = 0; pi < 5; pi++) {
                    var pa = (pi * 72 - 90) * Math.PI / 180;
                    pentPoints.push({x: baseSize * Math.cos(pa), y: baseSize * Math.sin(pa)});
                }
                shape = new fabric.Polygon(pentPoints, Object.assign({}, commonProps));
            } else if (type === 'heart') {
                var heartPath = 'M 0 -60 C -30 -110, -110 -110, -110 -50 C -110 10, -50 60, 0 110 ' +
                    'C 50 60, 110 10, 110 -50 C 110 -110, 30 -110, 0 -60 Z';
                shape = new fabric.Path(heartPath, Object.assign({
                    scaleX: baseSize / 110,
                    scaleY: baseSize / 110
                }, commonProps));
            } else {
                shape = new fabric.Circle(Object.assign({radius: baseSize}, commonProps));
            }

            shape.set('shadow', new fabric.Shadow({color: 'rgba(0,0,0,0.3)', blur: 15, offsetX: 5, offsetY: 5}));
            shape.set({name: 'Forma Principal'});
            bgShape = shape;
            canvas.add(shape);

            if (oldIndex !== -1) {
                shape.moveTo(oldIndex);
            } else {
                shape.sendToBack();
            }
            updateLayersPanel();
        };

        var currentShapeType = 'circle';

        // DECORATIONS.
        var toggleDecoration = function(type) {
            if (activeDecos[type]) {
                canvas.remove(activeDecos[type]);
                delete activeDecos[type];
            } else {
                var decoColor = $('#badge_color_deco').val();
                var strokeColor = $('#badge_color_border').val();
                var newDecoObj = null;

                if (type === 'ribbon') {
                    var ribbonPath = 'M -70 50 L -120 180 L -55 145 L 0 165 L 55 145 L 120 180 L 70 50 Z';
                    newDecoObj = new fabric.Path(ribbonPath, {
                        fill: decoColor, stroke: strokeColor, strokeWidth: 6,
                        left: centerX, top: centerY + 90, originX: 'center', originY: 'center', selectable: true,
                        shadow: new fabric.Shadow({color: 'rgba(0,0,0,0.3)', blur: 15, offsetX: 5, offsetY: 5})
                    });
                } else if (type === 'sunburst') {
                    var rays = [];
                    for (var ri = 0; ri < 24; ri++) {
                        var angle = (ri * 15) * Math.PI / 180;
                        var length = (ri % 2 === 0) ? 180 : 130;
                        rays.push({x: Math.cos(angle) * length, y: Math.sin(angle) * length});
                    }
                    newDecoObj = new fabric.Polygon(rays, {
                        fill: decoColor, stroke: strokeColor, strokeWidth: 6,
                        left: centerX, top: centerY, originX: 'center', originY: 'center', selectable: true,
                        shadow: new fabric.Shadow({color: 'rgba(0,0,0,0.3)', blur: 15, offsetX: 5, offsetY: 5})
                    });
                } else if (type === 'wings') {
                    var leftWing = new fabric.Polygon([
                        {x: 0, y: 0}, {x: -80, y: -60}, {x: -60, y: -20}, {x: -100, y: 10},
                        {x: -50, y: 30}, {x: -80, y: 60}, {x: 0, y: 40}
                    ], {fill: decoColor, stroke: strokeColor, strokeWidth: 5,
                        originX: 'right', originY: 'center', left: -80, top: 0});
                    var rightWing = new fabric.Polygon([
                        {x: 0, y: 0}, {x: 80, y: -60}, {x: 60, y: -20}, {x: 100, y: 10},
                        {x: 50, y: 30}, {x: 80, y: 60}, {x: 0, y: 40}
                    ], {fill: decoColor, stroke: strokeColor, strokeWidth: 5,
                        originX: 'left', originY: 'center', left: 80, top: 0});
                    newDecoObj = new fabric.Group([leftWing, rightWing], {
                        left: centerX, top: centerY + 10, originX: 'center', originY: 'center', selectable: true,
                        shadow: new fabric.Shadow({color: 'rgba(0,0,0,0.3)', blur: 15, offsetX: 5, offsetY: 5})
                    });
                } else if (type === 'crown') {
                    var crownPath = 'M -60 0 L -70 -50 L -35 -25 L 0 -60 L 35 -25 L 70 -50 L 60 0 Z';
                    newDecoObj = new fabric.Path(crownPath, {
                        fill: decoColor, stroke: strokeColor, strokeWidth: 4,
                        left: centerX, top: centerY - 130, originX: 'center', originY: 'center', selectable: true,
                        shadow: new fabric.Shadow({color: 'rgba(0,0,0,0.2)', blur: 10, offsetX: 3, offsetY: 3})
                    });
                } else if (type === 'laurels') {
                    var leftLaurel = [];
                    for (var li = 0; li < 6; li++) {
                        var la = (li * 25 + 200) * Math.PI / 180;
                        var lr = 130 + li * 3;
                        leftLaurel.push(new fabric.Ellipse({
                            rx: 12, ry: 22, fill: decoColor, stroke: strokeColor, strokeWidth: 2,
                            left: Math.cos(la) * lr, top: Math.sin(la) * lr,
                            angle: (li * 25 + 200 + 90), originX: 'center', originY: 'center'
                        }));
                    }
                    var rightLaurel = [];
                    for (var rl = 0; rl < 6; rl++) {
                        var ra = (-rl * 25 - 20) * Math.PI / 180;
                        var rr = 130 + rl * 3;
                        rightLaurel.push(new fabric.Ellipse({
                            rx: 12, ry: 22, fill: decoColor, stroke: strokeColor, strokeWidth: 2,
                            left: Math.cos(ra) * rr, top: Math.sin(ra) * rr,
                            angle: (-rl * 25 - 20 - 90), originX: 'center', originY: 'center'
                        }));
                    }
                    newDecoObj = new fabric.Group(leftLaurel.concat(rightLaurel), {
                        left: centerX, top: centerY + 20, originX: 'center', originY: 'center', selectable: true,
                        shadow: new fabric.Shadow({color: 'rgba(0,0,0,0.2)', blur: 8, offsetX: 2, offsetY: 2})
                    });
                } else if (type === 'stars_around') {
                    var starGroup = [];
                    for (var sg = 0; sg < 8; sg++) {
                        var sga = (sg * 45) * Math.PI / 180;
                        var sgr = 160;
                        var pts = [];
                        for (var sj = 0; sj < 10; sj++) {
                            var sgsr = sj % 2 === 0 ? 14 : 6;
                            var sgsa = (sj * 36 - 90) * Math.PI / 180;
                            pts.push({x: sgsr * Math.cos(sgsa), y: sgsr * Math.sin(sgsa)});
                        }
                        starGroup.push(new fabric.Polygon(pts, {
                            fill: decoColor, stroke: strokeColor, strokeWidth: 1,
                            left: Math.cos(sga) * sgr, top: Math.sin(sga) * sgr,
                            originX: 'center', originY: 'center'
                        }));
                    }
                    newDecoObj = new fabric.Group(starGroup, {
                        left: centerX, top: centerY, originX: 'center', originY: 'center', selectable: true,
                        shadow: new fabric.Shadow({color: 'rgba(0,0,0,0.2)', blur: 8, offsetX: 2, offsetY: 2})
                    });
                } else if (type === 'dots') {
                    var dotGroup = [];
                    for (var di = 0; di < 16; di++) {
                        var da = (di * 22.5) * Math.PI / 180;
                        var dr = 155;
                        var dotSize = (di % 2 === 0) ? 5 : 3;
                        dotGroup.push(new fabric.Circle({
                            radius: dotSize, fill: decoColor,
                            left: Math.cos(da) * dr, top: Math.sin(da) * dr,
                            originX: 'center', originY: 'center'
                        }));
                    }
                    newDecoObj = new fabric.Group(dotGroup, {
                        left: centerX, top: centerY, originX: 'center', originY: 'center', selectable: true,
                        shadow: new fabric.Shadow({color: 'rgba(0,0,0,0.15)', blur: 5, offsetX: 1, offsetY: 1})
                    });
                }

                if (newDecoObj) {
                    var decoNames = {
                        'ribbon': 'Listón', 'sunburst': 'Resplandor', 'wings': 'Alas',
                        'crown': 'Corona', 'laurels': 'Laureles', 'stars_around': 'Estrellas', 'dots': 'Puntos'
                    };
                    newDecoObj.set({name: decoNames[type]});
                    activeDecos[type] = newDecoObj;
                    canvas.add(newDecoObj);
                    newDecoObj.sendToBack();
                }
            }
            updateLayersPanel();
        };

        var reColorDecorations = function() {
            var decoColor = $('#badge_color_deco').val();
            var strokeColor = $('#badge_color_border').val();
            Object.values(activeDecos).forEach(function(decoObj) {
                if (decoObj.type === 'group') {
                    decoObj._objects.forEach(function(obj) {
                        obj.set({fill: decoColor, stroke: strokeColor});
                    });
                } else {
                    decoObj.set({fill: decoColor, stroke: strokeColor});
                }
            });
        };

        // ICON & TEXT.
        var createIcon = function() {
            var oldIndex = -1;
            if (iconObj) {
                oldIndex = canvas.getObjects().indexOf(iconObj);
                canvas.remove(iconObj);
            }
            var unicode = $('#badge_icon').val();
            iconObj = new fabric.Text(unicode, {
                fontFamily: '"Font Awesome 6 Free", FontAwesome, sans-serif',
                fontWeight: 900,
                fontSize: 100,
                fill: $('#badge_color_icon').val(),
                left: centerX,
                top: centerY - 20,
                originX: 'center',
                originY: 'center',
                textBaseline: 'bottom',
                selectable: true,
                name: 'Ícono Central'
            });
            canvas.add(iconObj);
            if (oldIndex !== -1) {
                iconObj.moveTo(oldIndex);
            }
            updateLayersPanel();
        };

        var createText = function() {
            var oldIndex = -1;
            if (textObj) {
                oldIndex = canvas.getObjects().indexOf(textObj);
                canvas.remove(textObj);
            }
            var fontSize = parseInt($('#badge_font_size').val()) || 26;
            var fontFamily = $('#badge_font').val() || 'Arial';
            textObj = new fabric.Text($('#badge_text').val(), {
                fontFamily: fontFamily,
                fontSize: fontSize,
                fontWeight: 'bold',
                fill: $('#badge_color_text').val(),
                left: centerX,
                top: centerY + 115,
                originX: 'center',
                originY: 'center',
                textAlign: 'center',
                textBaseline: 'bottom',
                selectable: true,
                name: 'Texto Principal'
            });
            canvas.add(textObj);
            if (oldIndex !== -1) {
                textObj.moveTo(oldIndex);
            }
            updateLayersPanel();
        };

        // EVENT HANDLERS.

        // Center all.
        $('#btn_center_all').click(function() {
            if (bgShape) {
                bgShape.set({left: centerX, top: centerY, scaleX: 1, scaleY: 1, angle: 0});
            }
            if (iconObj) {
                iconObj.set({left: centerX, top: centerY - 20, scaleX: 1, scaleY: 1, angle: 0});
            }
            if (textObj) {
                textObj.set({left: centerX, top: centerY + 115, scaleX: 1, scaleY: 1, angle: 0});
            }
            if (activeDecos.ribbon) {
                activeDecos.ribbon.set({left: centerX, top: centerY + 90, scaleX: 1, scaleY: 1, angle: 0});
            }
            if (activeDecos.sunburst) {
                activeDecos.sunburst.set({left: centerX, top: centerY, scaleX: 1, scaleY: 1, angle: 0});
            }
            if (activeDecos.wings) {
                activeDecos.wings.set({left: centerX, top: centerY + 10, scaleX: 1, scaleY: 1, angle: 0});
            }
            if (activeDecos.crown) {
                activeDecos.crown.set({left: centerX, top: centerY - 130, scaleX: 1, scaleY: 1, angle: 0});
            }
            if (activeDecos.laurels) {
                activeDecos.laurels.set({left: centerX, top: centerY + 20, scaleX: 1, scaleY: 1, angle: 0});
            }
            if (activeDecos.stars_around) {
                activeDecos.stars_around.set({left: centerX, top: centerY, scaleX: 1, scaleY: 1, angle: 0});
            }
            if (activeDecos.dots) {
                activeDecos.dots.set({left: centerX, top: centerY, scaleX: 1, scaleY: 1, angle: 0});
            }
            canvas.renderAll();
        });

        // Delete selected.
        $('#btn_delete_selected').click(function() {
            var active = canvas.getActiveObject();
            if (active) {
                removeTrackedObject(active);
                canvas.remove(active);
                canvas.discardActiveObject();
                canvas.renderAll();
                updateLayersPanel();
            }
        });

        // Keyboard delete.
        $(document).keydown(function(e) {
            if ((e.key === 'Delete' || e.key === 'Backspace') &&
                !$(e.target).is('input, textarea, select')) {
                var active = canvas.getActiveObject();
                if (active && active !== bgShape) {
                    removeTrackedObject(active);
                    canvas.remove(active);
                    canvas.discardActiveObject();
                    canvas.renderAll();
                    updateLayersPanel();
                }
            }
        });

        // Shape buttons.
        $('.shape-btn').click(function() {
            $('.shape-btn').removeClass('btn-primary').addClass('btn-outline-primary');
            $(this).removeClass('btn-outline-primary').addClass('btn-primary');
            currentShapeType = $(this).data('shape');
            createShape(currentShapeType);
            canvas.renderAll();
        });

        // Text input.
        $('#badge_text').on('input', function() {
            if (textObj) {
                textObj.set('text', $(this).val());
            } else {
                createText();
            }
            canvas.renderAll();
        });

        // Icon buttons.
        $('.icon-btn').click(function() {
            $('.icon-btn').removeClass('btn-primary').addClass('btn-outline-secondary');
            $(this).removeClass('btn-outline-secondary').addClass('btn-primary');
            $('#badge_icon').val($(this).data('icon'));
            createIcon();
            canvas.renderAll();
        });

        // Color changes for bg/border.
        $('#badge_color_bg, #badge_color_border').on('input', function() {
            if (bgShape) {
                bgShape.set('fill', $('#badge_color_bg').val());
                bgShape.set('stroke', $('#badge_color_border').val());
            }
            reColorDecorations();
            canvas.renderAll();
        });

        // Icon color.
        $('#badge_color_icon').on('input', function() {
            if (iconObj) {
                iconObj.set('fill', $(this).val());
            }
            canvas.renderAll();
        });

        // Text color.
        $('#badge_color_text').on('input', function() {
            if (textObj) {
                textObj.set('fill', $(this).val());
            }
            canvas.renderAll();
        });

        // Border width slider.
        $('#badge_border_width').on('input', function() {
            var w = parseInt($(this).val());
            $('#border_width_val').text(w);
            if (bgShape) {
                bgShape.set('strokeWidth', w);
            }
            canvas.renderAll();
        });

        // Opacity slider.
        $('#badge_opacity').on('input', function() {
            var op = parseInt($(this).val());
            $('#opacity_val').text(op);
            if (bgShape) {
                bgShape.set('opacity', op / 100);
            }
            canvas.renderAll();
        });

        // Font selector.
        $('#badge_font').change(function() {
            if (textObj) {
                textObj.set('fontFamily', $(this).val());
                canvas.renderAll();
            }
        });

        // Font size slider.
        $('#badge_font_size').on('input', function() {
            var size = parseInt($(this).val());
            $('#font_size_val').text(size);
            if (textObj) {
                textObj.set('fontSize', size);
                canvas.renderAll();
            }
        });

        // Collapsible sections.
        $('.badge-section-header').click(function() {
            $(this).closest('.badge-section').toggleClass('collapsed');
        });

        // Decoration buttons.
        $('.deco-btn').click(function() {
            $(this).toggleClass('btn-outline-primary btn-primary');
            toggleDecoration($(this).data('deco'));
            canvas.renderAll();
        });

        // Decoration color.
        $('#badge_color_deco').on('input', function() {
            reColorDecorations();
            canvas.renderAll();
        });

        // Image upload.
        var imageCount = 0;
        $('#badge_img_upload').on('change', function() {
            var file = this.files[0];
            if (!file) {
                return;
            }
            if (!file.type.match(/^image\//)) {
                // eslint-disable-next-line no-alert
                alert('Solo se permiten archivos de imagen (PNG, JPG, GIF, SVG, etc.).');
                return;
            }
            var reader = new FileReader();
            reader.onload = function(e) {
                fabric.Image.fromURL(e.target.result, function(img) {
                    var maxDim = 160;
                    var scale = Math.min(maxDim / img.width, maxDim / img.height, 1);
                    img.scale(scale);
                    imageCount++;
                    img.set({
                        left: centerX,
                        top: centerY,
                        originX: 'center',
                        originY: 'center',
                        selectable: true,
                        name: 'Imagen ' + imageCount
                    });
                    canvas.add(img);
                    canvas.setActiveObject(img);
                    canvas.renderAll();
                    updateLayersPanel();
                    $('#badge_img_feedback').text('Imagen "' + file.name + '" añadida al lienzo.').show();
                    setTimeout(function() {
                        $('#badge_img_feedback').hide();
                    }, 3000);
                });
            };
            reader.readAsDataURL(file);
            this.value = '';
        });

        // SAVE.
        $('#btn_save_badge').click(function() {
            var btn = $(this);
            var name = $('#badge_text').val();
            if (!name) {
                // eslint-disable-next-line no-alert
                alert('Nombre requerido');
                return;
            }
            btn.prop('disabled', true).text('Guardando...');

            try {
                canvas.discardActiveObject().renderAll();
                var dataURL = canvas.toDataURL({format: 'png', multiplier: 2});

                Ajax.call([{
                    methodname: 'local_automatic_badges_save_badge_design',
                    args: {
                        courseid: courseid,
                        name: name,
                        imagedata: dataURL
                    }
                }])[0].then(function(r) {
                    if (r.success) {
                        window.location.href = M.cfg.wwwroot + '/badges/edit.php?id=' + r.badgeid + '&action=badge';
                    } else {
                        // eslint-disable-next-line no-alert
                        alert(r.message);
                        btn.prop('disabled', false).html('<i class="fa fa-save"></i> Guardar Insignia');
                    }
                    return r;
                }).catch(function() {
                    // eslint-disable-next-line no-alert
                    alert('Error de conexión al guardar.');
                    btn.prop('disabled', false).html('<i class="fa fa-save"></i> Guardar Insignia');
                });
            } catch (e) {
                window.console.error(e);
                // eslint-disable-next-line no-alert
                alert('Error procesando imagen');
                btn.prop('disabled', false).html('<i class="fa fa-save"></i> Guardar Insignia');
            }
        });

        // INIT — draw default badge.
        setTimeout(function() {
            try {
                createShape('circle');
                createIcon();
                createText();
                // Activate ribbon decoration by default.
                $('.deco-btn[data-deco="ribbon"]').toggleClass('btn-outline-primary btn-primary');
                toggleDecoration('ribbon');
                canvas.renderAll();
            } catch (e) {
                window.console.error('Initialization error:', e);
            }
        }, 500);
    };

    return {
        init: init
    };
});
