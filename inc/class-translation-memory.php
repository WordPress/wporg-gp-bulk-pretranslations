<?php

namespace WordPressdotorg\GlotPress\Bulk_Pretranslations;

use WordPressdotorg\GlotPress\TranslationSuggestions\Translation_Memory_Client;

class Translation_Memory extends Pretranslation {

	/**
	 * Similarity threshold for the translation memory.
	 * If the similarity score is below this threshold, the suggestion is not used.
	 * Value between 0 and 1.
	 * @var float
	 */
	private $threshold = 1;

	public function get_suggestion_0( $original_id, $locale, $translation_set ) {
		if ( ! $this->should_pretranslate( $original_id, $translation_set ) ) {
			return false;
		}
		$suggestions = Translation_Memory_Client::query( $this->original->singular, $locale->slug );
		if ( empty( $suggestions ) ) {
			return false;
		}
		if ( is_wp_error( $suggestions ) ) {
			return false;
		}
		if ( $suggestions[0]['similarity_score'] < $this->threshold ) {
			return false;
		}
		return $suggestions[0]['translation'];
	}

}