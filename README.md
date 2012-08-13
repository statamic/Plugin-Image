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

**WxH#** : Center Crop to w/h, new image will be WxH, sized up if necessary

For example, with an image that is 150x150px and `dim=400x200#`

Image would be scaled up to 400x400 and then center cropped to fit 400x200

**WxH!** : Rescale the picture to w/h

For example, with an image that is 150x150px and `dim=400x200!`

Image would be scaled in height to 200px and by width to 400px

**WxH>** : (width is fixed)
**WxH<** : (height is fixed)

Image will be rescaled to fit w/h with one fixed dimension

**WxH(** : (width is fixed)
**WxH)** : (height is fixed)

Image will be cropped to fit w/h with one fixed dimension

### Quality

Pass an integer from 1 to 100 to set your desired quality.

    quality="80"