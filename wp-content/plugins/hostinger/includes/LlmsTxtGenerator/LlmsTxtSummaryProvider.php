<?php

namespace Hostinger\LlmsTxtGenerator;

use WP_Post;

defined( 'ABSPATH' ) || exit;

class LlmsTxtSummaryProvider {

    public const TARGET_LENGTH = 160;

    public const MINIMUM_WORDS      = 4;
    public const MINIMUM_CHARACTERS = 15;

    protected const HEADING_PATTERN           = '#<h[1-6][^>]*>.*?</h[1-6]>#is';
    protected const BLOCK_BOUNDARY_PATTERN    = '#</?(?:p|h[1-6]|li|dd|dt|div|section|article|aside|header|footer|blockquote|figcaption|td|th)[^>]*>|<br\s*/?>#i';
    protected const SENTENCE_BOUNDARY_PATTERN = '/(?<=[.!?])\s+|(?<=[.!?]["\')\]])\s+|(?<=[。！？])/u';
    protected const SENTENCE_END_PATTERN      = '/\p{L}.*[.!?。！？]["\')\]]?$/u';
    protected const DECODED_MARKUP_PATTERN    = '#</?[a-z][a-z0-9]*(?:\s+[a-z-]+=(?:"[^"]*"|\'[^\']*\'|[^\s"\'>]+))*\s*/?>#i';
    protected const WHITESPACE_PATTERN        = '/[\s\p{Z}]+/u';

    protected const LEGACY_PUNCTUATION_ENCODING = 'Windows-1252';

    protected const UTF8_SEQUENCE_PATTERN = '/
          [\x00-\x7F]
        | [\xC2-\xDF][\x80-\xBF]
        | \xE0[\xA0-\xBF][\x80-\xBF]
        | [\xE1-\xEC\xEE\xEF][\x80-\xBF]{2}
        | \xED[\x80-\x9F][\x80-\xBF]
        | \xF0[\x90-\xBF][\x80-\xBF]{2}
        | [\xF1-\xF3][\x80-\xBF]{3}
        | \xF4[\x80-\x8F][\x80-\xBF]{2}
        | (?<invalid>.)
    /xs';

    public function get_summary( WP_Post $post ): string {
        $excerpt = $this->to_plain_text( $post->post_excerpt );

        if ( $excerpt !== '' ) {
            return $excerpt;
        }

        return $this->get_content_summary( $post );
    }

    protected function get_content_summary( WP_Post $post ): string {
        $body_html = $this->replace_or_keep( self::HEADING_PATTERN, '', do_blocks( $post->post_content ) );

        foreach ( $this->split_into_text_blocks( $body_html ) as $text_block ) {
            $summary = $this->take_leading_sentences( $text_block );

            if ( $summary !== '' ) {
                return $summary;
            }
        }

        return '';
    }

    protected function take_leading_sentences( string $text ): string {
        $sentences = preg_split( self::SENTENCE_BOUNDARY_PATTERN, $text, -1, PREG_SPLIT_OFFSET_CAPTURE );

        if ( ! is_array( $sentences ) ) {
            return '';
        }

        $summary = '';

        foreach ( $sentences as $sentence ) {
            list( $sentence_text, $sentence_offset ) = $sentence;

            if ( ! $this->is_complete_sentence( $sentence_text ) ) {
                break;
            }

            $candidate = trim( substr( $text, 0, $sentence_offset + strlen( $sentence_text ) ) );

            if ( $this->has_enough_text( $summary ) && $this->character_count( $candidate ) > self::TARGET_LENGTH ) {
                break;
            }

            $summary = $candidate;
        }

        return $this->has_enough_text( $summary ) ? $summary : '';
    }

    protected function is_complete_sentence( string $sentence ): bool {
        return (bool) preg_match( self::SENTENCE_END_PATTERN, $sentence );
    }

    protected function has_enough_text( string $summary ): bool {
        if ( $this->character_count( $summary ) >= self::MINIMUM_CHARACTERS ) {
            return true;
        }

        $words = preg_split( self::WHITESPACE_PATTERN, $summary, -1, PREG_SPLIT_NO_EMPTY );

        if ( ! is_array( $words ) ) {
            return false;
        }

        return count( $words ) >= self::MINIMUM_WORDS;
    }

    /**
     * Core's mb_strlen() polyfill counts bytes unless it is given an encoding.
     */
    protected function character_count( string $text ): int {
        return mb_strlen( $text, 'UTF-8' );
    }

    protected function to_plain_text( string $html ): string {
        return implode( ' ', $this->split_into_text_blocks( $html ) );
    }

    protected function split_into_text_blocks( string $html ): array {
        $chunks = preg_split( self::BLOCK_BOUNDARY_PATTERN, $html );

        if ( ! is_array( $chunks ) ) {
            return array();
        }

        $text_blocks = array();

        foreach ( $chunks as $chunk ) {
            $text = $this->strip_markup( $chunk );

            if ( $text !== '' ) {
                $text_blocks[] = $text;
            }
        }

        return $text_blocks;
    }

    protected function strip_markup( string $html ): string {
        $text    = $this->to_valid_utf8( $html );
        $decoded = html_entity_decode( wp_strip_all_tags( strip_shortcodes( $text ) ), ENT_QUOTES | ENT_HTML5, 'UTF-8' );
        $plain   = $this->replace_or_keep( self::DECODED_MARKUP_PATTERN, '', $decoded );

        return trim( $this->replace_or_keep( self::WHITESPACE_PATTERN, ' ', $plain ) );
    }

    protected function to_valid_utf8( string $text ): string {
        if ( $this->is_valid_utf8( $text ) ) {
            return $text;
        }

        if ( function_exists( 'mb_convert_encoding' ) ) {
            $repaired = $this->repair_invalid_bytes( $text );

            if ( $repaired !== '' && $this->is_valid_utf8( $repaired ) ) {
                return $repaired;
            }
        }

        return wp_check_invalid_utf8( $text, true );
    }

    /**
     * Only the invalid bytes are converted; converting the whole string would re-encode
     * punctuation that is already correct.
     */
    protected function repair_invalid_bytes( string $text ): string {
        $repaired = preg_replace_callback(
            self::UTF8_SEQUENCE_PATTERN,
            static function ( array $matches ): string {
                if ( ! isset( $matches['invalid'] ) ) {
                    return $matches[0];
                }

                return (string) mb_convert_encoding( $matches['invalid'], 'UTF-8', self::LEGACY_PUNCTUATION_ENCODING );
            },
            $text
        );

        return $repaired ?? $text;
    }

    /**
     * wp_check_invalid_utf8() answers on the site's blog_charset rather than on the bytes,
     * and wp_is_valid_utf8() only exists from WordPress 6.9 while this plugin supports 5.5.
     */
    protected function is_valid_utf8( string $text ): bool {
        return preg_match( '//u', $text ) === 1;
    }

    protected function replace_or_keep( string $pattern, string $replacement, string $subject ): string {
        return preg_replace( $pattern, $replacement, $subject ) ?? $subject;
    }
}
