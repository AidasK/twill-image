<?php

namespace Croustille\Image\Models;

use Croustille\Image\Models\Interfaces\ImageSource;

class Image
{
    protected $backgroundColor;
    protected $layout;
    protected $loading;
    protected $lqip;
    protected $imgStyle;
    protected $sizes;
    protected $source;
    protected $height;
    protected $width;
    protected $wrapperClass;

    /**
     * @param ImageSource|array $source
     * @param array $args
     */
    public function __construct($source, array $args = [])
    {
        if (is_array($source)) {
            $this->data = $source;
        } else {
            $this->source = $source;
            $this->data = $this->getSourceData();
        }

        $this->setAttributes($args);
    }

    /**
     * Process arguments.
     *
     * List of valid arguments and their defaults:
     *
     * backgroundColor: specify css color [string]
     * lqip: use lqip [true|false]
     * layout: type of image (default fullWidth) [fullWidth|fixed|constraind]
     * laoding (default lazy) [lazy|eager]
     * width [int]
     * height [int]
     * sizes (default from config or autogenerated) [string]
     * imgStyle: css styles applied to <img> [array]
     * class: wrapper class [string]
     *
     * @param array $args
     * @return void
     */
    private function setAttributes($args)
    {
        $this->backgroundColor
          = $args['backgroundColor'] ??
          config('images.background_color') ??
          'transparent';

        $this->lqip
          = $args['lqip'] ??
          config('images.lqip') ??
          true;

        $this->layout = $args['layout'] ?? 'fullWidth';
        $this->loading = $args['loading'] ?? 'lazy';
        $this->width = $args['width'] ?? $this->data['width'];
        $this->height = $args['height'] ?? (isset($args['width']) ? $this->width / $this->data['width'] * $this->data['height'] : $this->data['height']);
        $this->sizes = $args['sizes'] ?? $this->data['sizes'] ?? $this->getDefaultSizes($this->layout);
        $this->wrapperClass = $args['class'] ?? false;

        $this->imgStyle = array_merge(
            [
                'bottom' => 0,
                'height' => '100%',
                'left' => 0,
                'margin' => 0,
                'max-width' => 'none',
                'padding' => 0,
                'position' => 'absolute',
                'right' => 0,
                'top' => 0,
                'width' => '100%',
                'object-fit' => 'cover',
                'object-position' => 'center center',
            ],
            $args['imgStyle'] ?? []
        );
    }

    private function getDefaultSizes($layout)
    {
        switch ($layout) {
            // If screen is wider than the max size, image width is the max size,
            // otherwise it's the width of the screen
            case 'constrained':
                return '(min-width:'.$this->width.'px) '.$this->width.'px, 100vw';

            // Image is always the same width, whatever the size of the screen
            case 'fixed':
                return $this->width.'px';

            // Image is always the width of the screen
            case 'fullWidth':
                return '100vw';

            default:
                return null;
        }
    }



    private function getPlaceholderPropsFromSource()
    {
        return $this->source->lqip();
    }


    private function getMainPropsFromSource()
    {
        return [
            'sources' => $this->source->srcSets(),
            'src' => $this->source->defaultSrc(),
        ];
    }

    public function getSourceData()
    {
        $placeholder = $this->getPlaceholderPropsFromSource();
        $main = $this->getMainPropsFromSource();

        return [
          'placeholder' => $placeholder,
          'main' => $main,
          'width' => $this->source->width(),
          'height' => $this->source->height(),
          'sizes' => $this->source->sizesAttr(),
          'alt' => $this->source->alt(),
        ];
    }

    private function getViewWrapperProps($layout = "fullWidth")
    {
        $style = [
            "position" => "relative",
            "overflow" => "hidden",
        ];

        $classes = "twill-image-wrapper";

        if ($layout === "fixed") {
            $style['width'] = $this->width."px";
            $style['height'] = $this->height."px";
        } elseif ($layout === "constrained") {
            $style['display'] = 'inline-block';
            $classes = "twill-image-wrapper twill-image-wrapper-constrained";
        }

        if ($this->backgroundColor) {
            $style['background-color'] = $this->backgroundColor;
        }

        if ($this->wrapperClass) {
            $classes = join(" ", [$classes, $this->wrapperClass]);
        }

        return [
            'classes' => $classes,
            'style' => $this->implodeStyles($style),
        ];
    }

    private function getViewPlaceholderProps($layout = "fullWidth")
    {
        $style = array_merge(
            $this->imgStyle,
            [
                'height' => '100%',
                'left' => 0,
                'position' => 'absolute',
                'top' => 0,
                'width' => '100%',
            ],
        );

        if ($this->backgroundColor) {
            $style['background-color'] = $this->backgroundColor;

            if ($layout === 'fixed') {
                $style['width'] = $this->width.'px';
                $style['height'] = $this->height.'px';
                $style['background-color'] = $this->backgroundColor;
                $style['position'] = 'relative';
            } elseif ($layout === 'constrained') {
                $style['position'] = 'absolute';
                $style['top'] = 0;
                $style['left'] = 0;
                $style['bottom'] = 0;
                $style['right'] = 0;
            } elseif ($layout === 'fullWidth') {
                $style['position'] = 'absolute';
                $style['top'] = 0;
                $style['left'] = 0;
                $style['bottom'] = 0;
                $style['right'] = 0;
            }
        }

        $style['opacity'] = 1;
        $style['transition'] =  "opacity 500ms linear";

        return array_merge(
            [
            'style' => $this->implodeStyles($style),
            ],
            !$this->lqip ? ['src' => null] : []
        );
    }

    private function getViewMainProps($isLoading)
    {
        $style = array_merge(
            [
                'transition' => 'opacity 500ms ease 0s',
                'transform' => 'translateZ(0px)',
                'transition' => 'opacity 250ms linear',
                'will-change' => 'opacity',
            ],
            $this->imgStyle
        );

        if ($this->backgroundColor) {
            $style['background-color'] = $this->backgroundColor;
        }

        $style['opacity'] = $this->loading === 'lazy' ? 0 : 1;

        return [
            'loading' => $this->loading,
            'shouldLoad' => $isLoading,
            'style' => $this->implodeStyles($style),
        ];
    }

    private function implodeStyles($style)
    {
        return implode(
            ';',
            array_map(
                function ($property, $value) {
                    return "$property:$value";
                },
                array_keys($style),
                $style
            )
        );
    }

    private function buildViewData($layout)
    {
        $data = $this->data;
        $wrapper = $this->getViewWrapperProps($layout);
        $placeholder = array_merge(
            $data['placeholder'],
            $this->getViewPlaceholderProps($layout)
        );
        $main = array_merge(
            $data['main'],
            $this->getViewMainProps($this->loading === "eager")
        );

        return [
          'layout' => $layout,
          'wrapper' => $wrapper,
          'placeholder' => $placeholder,
          'main' => $main,
          'alt' => $data['alt'],
          'width' => $this->width,
          'height' => $this->height,
          'sizes' => $this->sizes,
        ];
    }

    public function view()
    {
        return view('image::wrapper', $this->buildViewData($this->layout));
    }
}
