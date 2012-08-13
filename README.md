Statamic Image Plugin
================================

The Image plugin is used for resizing, cropping, and manipulating your images on the fly, right from your template code. All images are cached and timestamped so caches are refreshed when files update.

## Installing
1. Download the zip file (or clone via git) and unzip it or clone the repo into `/_add-ons/`.
2. Ensure the folder name is `image` (Github timestamps the download folder).
3. Enjoy.

## Example Tag

    {{ image src="/url_path/to/image" dim="400x300" }}
      <img src="{{ url }}" width={{ width }} height={{ height }} />
    {{ /image }}

## Parameters

### Dimension (`dim`)
The dimension parameter is a swiss-army knife, all-in-one style parameter. Pass it your width x height (WxH), and your resize/crop flag and you're done.

    dim="400x300>"

#### Resize/Crop Flags

**WxH#**

Center Crop to W/H, new image will be WxH, sized up if necessary

For example, with an image that is 150x150px and `dim=400x200#`, the image would be scaled up to 400x400 and then center cropped to fit 400x200

**WxH!**

Rescale the image to W/H. For example, with an image that is 150x150px and `dim=400x200!`, the image would be scaled to 200px high and by 400px wide

**WxH>**

Image will be rescaled to fit W/H with a fixed **width**

**WxH<**

Image will be rescaled to fit W/H with a fixed **height**

**WxH(**

Image will be cropped to fit W/H with a fixed **width**

**WxH)**

Image will be cropped to fit W/H with a fixed **height**

### Quality (`1 - 100`)

Pass an integer from 1 to 100 to set your desired quality.

    quality="80"