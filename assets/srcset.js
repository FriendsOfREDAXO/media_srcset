var srcset = {
    getImageElement : function(element)
    {
        if(element.nodeName.toUpperCase() === 'IMG')
        {
            return element;
        }
        else if(picture = this.getPictureElement())
        {
            return picture.query('img');
        }

        return null;
    },

    getPictureElement : function(element)
    {
        while (element.parentElement)
        {
            if(element.nodeName.toUpperCase() === 'PICTURE')
            {
                return element;
            }

            element = element.parentElement;
        }

        return null;
    },

    getElementWidth : function(element)
    {
        if(picture = this.getPictureElement(element))
        {
            width = picture.offsetWidth;
        }
        else
        {
            width = element.offsetWidth;
        }

        width*= window.devicePixelRatio || 1;

        return width;
    },

    getSrcsetItems : function(element)
    {
        if(element.getAttribute('data-srcset'))
        {
            var srcset = element.getAttribute('data-srcset').split(','),
                srcsetlength = srcset.length,
                files = {};

            for(var j = 0; j < srcsetlength; j++)
            {
                var set = srcset[j].trim(),
                    file = set.substr(0, set.indexOf(' ')),
                    width = parseInt(set.substr(set.indexOf(' ')+1).replace(/[^0-9]/, ''));

                if(!isNaN(width) && file)
                {
                    files[width] = file;
                }
            }

            return files;
        }

        return null;
    },

    getValidSrc : function(element)
    {
        var srcsets = this.getSrcsetItems(element);

        if(srcsets)
        {
            var widths = Object.keys(srcsets).sort(function(a,b) { return parseFloat(a) - parseFloat(b); }),
                width = this.getElementWidth(element),
                newwidth = widths[widths.length - 1];

            for(var j = widths.length-1; j > -1; j--)
            {
                if(parseFloat(widths[j]) > parseFloat(width))
                {
                    newwidth = widths[j];
                }
                else
                {
                    break;
                }
            }

            if(newwidth)
            {
                return srcsets[newwidth];
            }
        }

        if(image = this.getImageElement(element))
        {
            return image.getAttribute('src');
        }
    },

    setSrc : function(element, src)
    {
        var image = this.getImageElement(element)
        if(image)
        {
            var newimage = new Image();
            newimage.onload = function(image){ image.src = this.src; }.bind(newimage, image);
            newimage.src = src;

            return true;
        }

        return false;
    },

    updateSrc : function(element)
    {
        var image = this.getImageElement(element);
        if(image)
        {
            var src = image.src,
                newsrc = this.getValidSrc(element);

            if(src != newsrc)
            {
                this.setSrc(image, newsrc);
            }
        }
    },

    updateElements : function()
    {
        var elements = document.querySelectorAll('[data-srcset]'),
            length = elements.length;

        for(var i = 0; i < length; i ++)
        {
            this.updateSrc(elements[i]);
        }
    },

    onResize : function()
    {
        window.clearTimeout(this.timeout);
        this.timeout = window.setTimeout(this.updateElements.bind(this), 10);
    },

    init : function()
    {
        this.updateElements();
        window.addEventListener("resize", srcset.onResize.bind(srcset) );
        // this.addDomChangeListener();
    },

    // TODO : update after DOMCHANGE!
    addDomChangeListener : function()
    {
        var MutationObserver = window.MutationObserver || window.WebKitMutationObserver;

        if(MutationObserver)
        {

            this.observer = new MutationObserver(function(mutations) {
                for(var i = 0; i < mutations.length; i++)
                {
                    // check if this element has resized AND contains a data-srcset element
                    this.updateSrc(element);
                }
            }.bind(this));

            this.observer.observe(document, {
              subtree: true,
              attributes: true
            });
        }
    },

    removeDomChangeListener : function()
    {
        this.observer.disconnect();
    }
}


document.addEventListener("DOMContentLoaded", srcset.init.bind(srcset) );
