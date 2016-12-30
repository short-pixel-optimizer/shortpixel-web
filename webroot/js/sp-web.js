/**
 * Created by simon on 11.12.2016.
 */
(function($){
    $(document).ready(function(){
        $("#select-folder").click(function(){
            $(".sp-folder-picker-shade").css("display", "block");
            $(".sp-folder-picker").fileTree({
                script: ShortPixel.browseContent,
                //folderEvent: 'dblclick',
                multiFolder: false
                //onlyFolders: true
            });
        });
        $(".sp-folder-picker-popup input.select-folder-cancel").click(function(){
            $(".sp-folder-picker-shade").css("display", "none");
        });
        $(".sp-folder-picker-popup input.select-folder").click(function(){
            var subPath = $("UL.jqueryFileTree LI.directory.selected A").attr("rel");
            if(subPath) {
                var fullPath = subPath;
                if(fullPath.slice(-1) == '/') fullPath = fullPath.slice(0, -1);
                $("#folder").val(fullPath);
                //if folder has custom options, set the corresponding options fields
                var options = ShortPixel.getFolderOptions({folder: subPath});
                if(typeof options === 'object' && typeof options.lossy !== 'undefined') {
                    $("#type-" + (options.lossy == 1 ? 'lossy' : 'lossless')).prop("checked", true);
                    $("#removeExif").prop("checked", (options.keep_exif == 0 ? true : false));
                    $("#cmyk2rgb").prop("checked", (options.cmyk2rgb == 1 ? true : false));
                    $("#resize").prop("checked", (options.resize & 1 ? true : false));
                    $("#width").val(options.resize_width);
                    $("#height").val(options.resize_height);
                    $("#resize_type_" + (options.resize & 2 ? 'inner' : 'outer')).prop("checked", true);
                    $("#webp").prop("checked", (options.convertto == '+webp' ? true : false));
                    $('<div><h3 class="success" id="info-message">Folder-specific options loaded, please check below.</h3></div>').insertBefore("#options-header");

                }
                $(".sp-folder-picker-shade").css("display", "none");
            } else {
                alert("Please select a folder from the list.");
            }
        });
        ShortPixel.enableResize("#resize");
        $("#resize").change(function(){ ShortPixel.enableResize(this); });
    });

})(jQuery);

var ShortPixel = function() {

    function browseContent(browseData) {
        browseData.action = 'shortpixel_browse_content';
        var browseResponse = "";
        jQuery.ajax({
            type: "POST",
            url: window.location,
            data: browseData,
            success: function(response) {
                browseResponse = response;
            },
            async: false
        });
        return browseResponse;
    }

    function getFolderOptions(browseData) {
        browseData.action = 'shortpixel_folder_options';
        var browseResponse = "";
        jQuery.ajax({
            type: "POST",
            url: window.location,
            data: browseData,
            success: function(response) {
                browseResponse = response;
            },
            async: false
        });
        try {
            return JSON.parse(browseResponse);
        } catch(ex) {
            return {};
        }
    }

    function optimize(folder) {
        $.ajax({
            type: "POST",
            url: window.location.split("?")[0],
            data: {
                action: 'shortpixel_optimize',
                folder: folder
            },
            success: function(response) {
                try {
                    var data = JSON.parse(response);
                } catch (e) {
                    console.log("Unrecognized response, retrying in 10 sec. (" + response + ")");
                    setTimeout(function(){optimize(folder);}, 10000);
                    return;
                }
                if(data.status.code < 0) { //an error occured
                    if($("#error-message").length > 0) {
                        $("#error-message").html(data.status.message);
                    } else {
                        $('<div><h3 class="error" id="error-message">' + data.status.message + '</h3></div>').insertBefore("#totalFiles");
                    }
                    if(data.status.code != -403) {
                        setTimeout(function(){optimize(folder);}, 15000);
                    }
                    return;
                }
                if(data.status.code == 1) {
                    $("#doneFiles").val(parseInt($("#doneFiles").val()) + data.succeeded.length + data.failed.length + data.same.length)
                    var percent = Math.min(100.0, 100.0 * (parseInt($("#doneFiles").val())) / (parseInt($("#totalFiles").val())));
                    progressUpdate(percent.toFixed(1), "");
                    if(   data.succeeded.length + data.pending.length + data.same.length + data.failed.length == 0) {
                        if(ShortPixel.emptyConsecutiveResponses > 3 || percent == 100.0) {
                            if(ShortPixel.sliderConsumerId !== false) {
                                clearInterval(ShortPixel.sliderConsumerId);
                                ShortPixel.sliderConsumerId = false;
                            }
                            //finished!
                            window.location.reload();
                            return;
                        }
                        ShortPixel.emptyConsecutiveResponses++;
                    } else {
                        ShortPixel.emptyConsecutiveResponses = 0;
                    }
                    for(var i = 0; i < data.succeeded.length; i++) {
                        item = data.succeeded[i];
                        //preload the images
                        if(item.OriginalURL.split('.').pop().toLowerCase() !== 'pdf') {
                            item.imageOrig = new Image();
                            item.imageOpt = new Image();
                            item.imageOrig.src = item.OriginalURL;
                            item.imageOpt.src = item.LossyURL;
                        }
                        ShortPixel.sliderQueue.enqueue(data.succeeded[i]);
                    }
                    if(ShortPixel.sliderConsumerId === false) {
                        sliderUpdate();
                        ShortPixel.sliderConsumerId = setInterval(sliderUpdate, ShortPixel.sliderFrequencyMs);
                    }
                    setTimeout(function(){optimize(folder);}, 1000);
                }
                else if(data.status.code == 0)
                {
                    // timeout, retry in 10 sec.
                    setTimeout(function(){optimize(folder);}, 10000);
                }
            },
            error : function(x, t, m) {
                if(t==="timeout") {
                    console.log("got timeout, retrying in 10 sec...");
                } else {
                    console.log("got error " + t + ", retrying in 10 sec...");
                }
                setTimeout(function(){optimize(folder);}, 10000);
            }
        });
    }

    function progressUpdate(percent, message) {
        var progress = $("#bulk-progress");
        if(progress.length) {
            $(".progress-left", progress).css("width", percent + "%");
            $(".progress-img", progress).css("left", percent + "%");
            if(percent > 24) {
                $(".progress-img span", progress).html("");
                $(".progress-left", progress).html(percent + "%");
            } else {
                $(".progress-img span", progress).html(percent + "%");
                $(".progress-left", progress).html("");
            }
            jQuery(".bulk-estimate").html(message);
        }
    }

    function sliderUpdate(){
        var id = ShortPixel.counter++
        var item = ShortPixel.sliderQueue.dequeue();
        if(typeof item === 'undefined') { //empty queue - make slider slower
            ShortPixel.sliderFrequencyMs += 500;
            return;
        }
        if(ShortPixel.sliderQueue.getLength() > 15) {
            if(ShortPixel.sliderFrequencyMs >= 2500) {
                //make slider faster but not less than 2 seconds
                ShortPixel.sliderFrequencyMs -= 500;
            } else {
                //get rid of every other item
                ShortPixel.sliderQueue.dequeue();
            }

        }
        var percent = item.PercentImprovement;
        var filename = item.SavedFile.split('/').pop();
        var ext = filename.split('.').pop().toLowerCase();
        var thumb = (ext === 'pdf' ? "/img/logo-pdf.png" : item.LossyURL);
        var bkThumb = (ext === 'pdf' ? "/img/logo-pdf.png" : item.OriginalURL);

        var oldSlide = jQuery(".bulk-slider div.bulk-slide:first-child");
        if(oldSlide.attr("id") != "empty-slide") {
            oldSlide.hide();
        }
        oldSlide.css("z-index", 1000);
        $(".bulk-img-opt", oldSlide).attr("src", "");
        if(bkThumb.length > 0) {
            $(".bulk-img-orig", oldSlide).attr("src", "");
        }

        var newSlide = oldSlide.clone();
        newSlide.attr("id", "slide-" + id);
        $(".bulk-img-opt", newSlide).attr("src", thumb);
        if(bkThumb.length > 0) {
            $(".img-original", newSlide).css("display", "inline-block");
            $(".bulk-img-orig", newSlide).attr("src", bkThumb);
        } else {
            $(".img-original", newSlide).css("display", "none");
        }
        $(".bulk-opt-percent", newSlide).html('<input type="text" class="dial" value="' + percent + '"/>');

        //debugger;
        $(".bulk-slider").append(newSlide);
        ShortPixel.percentDial("#" + newSlide.attr("id") + " .dial", 100);

        $(".bulk-slider-container span.filename").html("&nbsp;&nbsp;" + filename);
        if(oldSlide.attr("id") == "empty-slide") {
            oldSlide.remove();
            $(".bulk-slider-container").css("display", "block");
        } else {
            oldSlide.animate({ left: oldSlide.width() + oldSlide.position().left }, 'slow', 'swing', function(){
                oldSlide.remove();
                newSlide.fadeIn("slow");
            });
        }
    }

    function hideSlider() {
        jQuery(".bulk-slider-container").css("display", "none");
    }

    function percentDial(query, size) {
        jQuery(query).knob({
            'readOnly': true,
            'width': size,
            'height': size,
            'fgColor': '#1CAECB',
            'format' : function (value) {
                return value + '%';
            }
        });
    }

    function enableResize(elm) {
        if($(elm).is(':checked')) {
            $("#width,#height").removeAttr("disabled");
        } else {
            $("#width,#height").attr("disabled", "disabled");
        }
    }

    return {
        //status
        counter : 0,
        sliderQueue : new Queue(),
        sliderConsumerId : false,
        sliderFrequencyMs : 3000,
        emptyConsecutiveResponses : 0,
        //methods
        browseContent : browseContent,
        getFolderOptions: getFolderOptions,
        optimize : optimize,
        progressUpdate : progressUpdate,
        sliderUpdate : sliderUpdate,
        hideSlider : hideSlider,
        percentDial : percentDial,
        enableResize : enableResize
    }
}();

/*
 Queue.js
 A function to represent a queue

 Created by Stephen Morley - http://code.stephenmorley.org/ - and released under
 the terms of the CC0 1.0 Universal legal code:

 http://creativecommons.org/publicdomain/zero/1.0/legalcode
 */

/* Creates a new queue. A queue is a first-in-first-out (FIFO) data structure -
 * items are added to the end of the queue and removed from the front.
 */
function Queue(){

    // initialise the queue and offset
    var queue  = [];
    var offset = 0;

    // Returns the length of the queue.
    this.getLength = function(){
        return (queue.length - offset);
    }

    // Returns true if the queue is empty, and false otherwise.
    this.isEmpty = function(){
        return (queue.length == 0);
    }

    /* Enqueues the specified item. The parameter is:
     *
     * item - the item to enqueue
     */
    this.enqueue = function(item){
        queue.push(item);
    }

    /* Dequeues an item and returns it. If the queue is empty, the value
     * 'undefined' is returned.
     */
    this.dequeue = function(){

        // if the queue is empty, return immediately
        if (queue.length == 0) return undefined;

        // store the item at the front of the queue
        var item = queue[offset];

        // increment the offset and remove the free space if necessary
        if (++ offset * 2 >= queue.length){
            queue  = queue.slice(offset);
            offset = 0;
        }

        // return the dequeued item
        return item;

    }

    /* Returns the item at the front of the queue (without dequeuing it). If the
     * queue is empty then undefined is returned.
     */
    this.peek = function(){
        return (queue.length > 0 ? queue[offset] : undefined);
    }

}