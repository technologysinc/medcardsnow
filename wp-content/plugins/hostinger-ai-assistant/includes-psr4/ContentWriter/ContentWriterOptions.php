<?php

namespace Hostinger\AiAssistant\ContentWriter;

use Hostinger_Ai_Assistant_Content_Generation;

if ( ! defined( 'ABSPATH' ) ) {
    die;
}

class ContentWriterOptions {
    private Hostinger_Ai_Assistant_Content_Generation $content_generation;

    public function __construct( Hostinger_Ai_Assistant_Content_Generation $content_generation ) {
        $this->content_generation = $content_generation;
    }

    public function get_content_types(): array {
        $content_types = array();

        foreach ( $this->content_generation->get_public_post_types() as $post_type ) {
            $post_type_object = get_post_type_object( $post_type );

            if ( ! $post_type_object ) {
                continue;
            }

            $content_types[] = array(
                'value' => $post_type,
                'label' => $post_type_object->label,
            );
        }

        return $content_types;
    }
}
