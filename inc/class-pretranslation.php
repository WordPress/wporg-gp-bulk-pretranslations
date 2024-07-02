<?php

namespace WordPressdotorg\GlotPress\Bulk_Pretranslations;

use GP;
use GP_Locale;
use GP_Original;
use GP_Translation_Set;

abstract class Pretranslation {

	/**
	 * @var GP_Original
	 */
	protected ?GP_Original $original=null;

	/**
	 * @var string
	 */
	protected string $suggestion_0 = '';

	/**
	 * Get the suggestion for the translation.
	 *
	 * Only works for strings with no plural forms.
	 *
	 * @param int                $original_id     The original ID.
	 * @param GP_Locale          $locale          The locale.
	 * @param GP_Translation_Set $translation_set The translation set.
	 *
	 * @return false|string
	 */
	abstract public function get_suggestion_0( int $original_id, GP_Locale $locale, GP_Translation_Set $translation_set );

	/**
	 * Check if a string should be pre-translated.
	 *
	 * @param int                $original_id     The original ID.
	 * @param GP_Translation_Set $translation_set The translation set.
	 *
	 * @return bool
	 */
	protected function should_pretranslate( int $original_id, GP_Translation_Set $translation_set ): bool {
		$this->original = GP::$original->get( $original_id );
		if ( ! $this->original ) {
			return false;
		}
		if ( ! is_null( $this->original->plural ) ) {
			return false;
		}

		// We don't pre translate string with a current translation.
		$translations = GP::$translation->find(
			array(
				'original_id' => $original_id,
				'translation_set_id' => $translation_set->id,
				'status' => 'current',
			)
		);
		if ( ! empty( $translations ) ) {
			return false;
		}

		return true;
	}
}