<?php

namespace JohannSchopplich\Headless\Block;

class Image extends \Kirby\Cms\Block
{
    public function toArray(): array
    {
        $block = parent::toArray();
        $image = $this->content->get('image')->toFile();

        if ($image) {
            $block['content']['resolved']['image'] = [
                'url'    => $image->url(),
                'width'  => $image->width(),
                'height' => $image->height(),
                'srcset' => $image->srcset(),
                'alt'    => $image->alt()->value()
            ];
        }

        return $block;
    }
}
