<?php

use WordPressdotorg\GlotPress\Bulk_Pretranslations\OpenAI\Open_AI;
use WordPressdotorg\GlotPress\TranslationSuggestions\Translation_Memory_Client;

/**
 * Plugin Name: WordPress.org bulk pre-translations
 * Description: Pre-translate strings in GlotPress projects using internal and external tools.
 * Version:     0.1.0
 * Author:      WordPress.org
 * Author URI:  http://wordpress.org/
 * License:     GPLv2 or later
 * Text Domain: wporg-gp-bulk-pretranslations
 */

class GP_Bulk_Pretranslations extends GP_Route {

	private static $instance = null;

	public static function init() {
		self::get_instance();
	}

	public static function get_instance() {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	public function __construct() {
		parent::__construct();
		add_action( 'gp_pre_tmpl_load', array( $this, 'register_js' ), 5 );
		add_action( 'gp_translation_set_bulk_action', array( $this, 'add_pretranslation_options' ) );
        add_action( 'gp_translation_set_bulk_action_post', array( $this, 'store_pretanslations' ), 10, 4 );
    }

    /**
     * Register the JavaScript file.
     */
    public function register_js() {
	            wp_enqueue_script( 'wporg-gp-bulk-pretranslations',
                    plugins_url( 'assets/js/wporg-gp-bulk-pretranslations.js', __FILE__ ),
		            array( 'jquery', 'gp-common' ),
		            filemtime( __DIR__ . '/assets/js/wporg-gp-bulk-pretranslations.js' )
                );
                gp_enqueue_scripts( 'wporg-gp-bulk-pretranslations' );
    }

    /**
     * Add pretranslation options to the bulk actions dropdown.
     *
     * @param GP_Translation_Set $translation_set The translation set.
     */
    public function add_pretranslation_options( $translation_set ) {
	    $can_approve = $this->can( 'approve', 'translation-set', $translation_set->id );
        if ( ! $can_approve ) {
		    return;
	    }

	    $gp_default_sort = get_user_option( 'gp_default_sort' );
	    $openai_key      = gp_array_get( $gp_default_sort, 'openai_api_key', false );
	    $deepl_api_key   = gp_array_get( $gp_default_sort, 'deepl_api_key', false );
        $locale          = $translation_set->locale;

	    echo '<optgroup label="Pre translate selected rows with">';
	    echo '<option value="bulk-pretranslation-tm">' . esc_html__( 'Translation Memory', 'glotpress' ) . '</option>';
	    if ( $openai_key ) {
            echo '<option value="bulk-pretranslation-openai">' . esc_html__( 'OpenAI', 'glotpress' ) . '</option>';
        }
        if ( $deepl_api_key && $this->get_deepl_locale( $locale ) ) {
            echo '<option value="bulk-pretranslation-deepl">' . esc_html__( 'DeepL', 'glotpress' ) . '</option>';
        }
        echo '</optgroup>';
    }

    /**
     * Store the pretranslations.
     *
     * @param GP_Project         $project          The project.
     * @param GP_Locale          $locale           The locale.
     * @param GP_Translation_Set $translation_set  The translation set.
     * @param array              $bulk             The bulk action data.
     */
    public function store_pretanslations($project, $locale, $translation_set, $bulk ) {
	    $can_approve = $this->can( 'approve', 'translation-set', $translation_set->id );
	    if ( ! $can_approve ) {
		    return;
	    }
//        if ( 'bulk-pretranslation-tm' === $bulk['action'] ) {
//            $this->bulk_pretranslation_tm( $locale, $translation_set, $bulk );
//        }
//        if ( 'bulk-pretranslation-openai' === $bulk['action'] ) {
//            $this->bulk_pretranslation_openai( $locale, $translation_set, $bulk );
//        }
//        if ( 'bulk-pretranslation-deepl' === $bulk['action'] ) {
//            $this->bulk_pretranslation_deepl( $locale, $translation_set, $bulk );
//        }
	    $current_user_id       = get_current_user_id();
	    $pretranslations_added = 0;
	    foreach ( $bulk['row-ids'] as $original_id ) {
		    $translation_0 = null;
		    $original = $this->should_pretranslate( $original_id, $translation_set );
		    if ( $original ) {
			    if ( 'bulk-pretranslation-tm' === $bulk['action'] ) {
				    $translation_0 = $this->get_suggestion_from_tm( $original, $locale );
			    }
				if ( 'bulk-pretranslation-openai' === $bulk['action'] ) {
					$output        = $this->get_suggestion_from_openai( $original, $locale );
					$message       = $output['choices'][0]['message'];
					$translation_0 = trim( trim( $message['content'] ), '"' );
					if ( $translation_0 ) {
						$this->update_openai_tokens_used( $output['usage']['total_tokens'] );
					}
				}
				if ( 'bulk-pretranslation-deepl' === $bulk['action'] ) {
					$translation_0 = $this->get_suggestion_from_deepl( $original, $locale );
					if ( $translation_0 ) {
						$this->update_deepl_chars_used( $original->singular );
					}
				}
			    if ( $translation_0 ) {
				    $translation_created = $this->store_translation( $original_id, $translation_set->id, $translation_0, $current_user_id );
				    if ( $translation_created ) {
					    $pretranslations_added++;
				    }
			    }
		    }
	    }
	    $this->set_notice( $pretranslations_added );
    }

    /**
     * Pretranslate strings using the Translation Memory.
     *
     * @param GP_Locale          $locale           The locale.
     * @param GP_Translation_Set $translation_set  The translation set.
     * @param array              $bulk             The bulk action data.
     */
    private function bulk_pretranslation_tm( $locale, $translation_set, $bulk ) {
	    $current_user_id             = get_current_user_id();
	    $pretranslations_added = 0;
	    foreach ( $bulk['row-ids'] as $original_id ) {
			$original = $this->should_pretranslate( $original_id, $translation_set );
		    if ( $original ) {
				$translation_0 = $this->get_suggestion_from_tm( $original, $locale );
				if ( $translation_0 ) {
					$translation_created = $this->store_translation( $original_id, $translation_set->id, $translation_0, $current_user_id );
					if ( $translation_created ) {
						$pretranslations_added++;
					}
				}
		    }
	    }
		$this->set_notice( $pretranslations_added );
    }

	/**
	 * Gets the Deepl locale.
	 *
	 * @param string $locale The WordPress locale.
	 *
	 * @return string
     * Todo: review the list in the DeepL API.
	 */
	private function get_deepl_locale( string $locale ): string {
		$available_locales = array(
			'bg'    => 'BG',
			'cs'    => 'CS',
			'da'    => 'DA',
			'de'    => 'DE',
			'el'    => 'EL',
			'en-gb' => 'EN-GB',
			'es'    => 'ES',
			'et'    => 'ET',
			'fi'    => 'FI',
			'fr'    => 'FR',
			'hu'    => 'HU',
			'id'    => 'ID',
			'it'    => 'IT',
			'ja'    => 'JA',
			'ko'    => 'KO',
			'lt'    => 'LT',
			'lv'    => 'LV',
			'nb'    => 'NB',
			'nl'    => 'NL',
			'pl'    => 'PL',
			'pt'    => 'PT-PT',
			'pt-br' => 'PT-BR',
			'ro'    => 'RO',
			'ru'    => 'RU',
			'sk'    => 'SK',
			'sl'    => 'SL',
			'sv'    => 'SV',
			'tr'    => 'TR',
			'uk'    => 'UK',
			'zh-cn' => 'ZH',
		);
		if ( array_key_exists( $locale, $available_locales ) ) {
			return $available_locales[ $locale ];
		}
		return '';
	}

	/**
	 * Gets the formality of the language.
	 *
	 * @param string $locale   The locale.
	 * @param string $set_slug The set slug.
	 *
	 * @return string
	 */
	private function get_language_formality( string $locale, string $set_slug ): string {
		$lang_informality = array(
			'BG'    => 'prefer_more',
			'CS'    => 'prefer_less',
			'DA'    => 'prefer_less',
			'DE'    => 'prefer_less',
			'EL'    => 'prefer_more',
			'EN-GB' => 'prefer_less',
			'ES'    => 'prefer_less',
			'ET'    => 'prefer_less',
			'FI'    => 'prefer_less',
			'FR'    => 'prefer_more',
			'HU'    => 'prefer_more',
			'ID'    => 'prefer_more',
			'IT'    => 'prefer_less',
			'JA'    => 'prefer_more',
			'KO'    => 'prefer_less',
			'LT'    => 'prefer_more',
			'LV'    => 'prefer_less',
			'NB'    => 'prefer_less',
			'NL'    => 'prefer_less',
			'PL'    => 'prefer_less',
			'PT-BR' => 'prefer_less',
			'PT-PT' => 'prefer_more',
			'RO'    => 'prefer_less',
			'RU'    => 'prefer_more',
			'SK'    => 'prefer_less',
			'SL'    => 'prefer_less',
			'SV'    => 'prefer_less',
			'TR'    => 'prefer_less',
			'UK'    => 'prefer_more',
			'ZH'    => 'prefer_more',
		);

		if ( ( 'DE' == $locale || 'NL' == $locale ) && 'formal' == $set_slug ) {
			return 'prefer_more';
		}
		if ( array_key_exists( $locale, $lang_informality ) ) {
			return $lang_informality[ $locale ];
		}

		return 'default';
	}

	/**
	 * Updates the number of characters used by DeepL.
	 *
	 * @param string $original_singular The singular from the original string.
	 */
	private function update_deepl_chars_used( string $original_singular ) {
		$gp_external_translations = get_user_option( 'gp_external_translations' );
		$deepl_chars_used         = gp_array_get( $gp_external_translations, 'deepl_chars_used', 0 );
		if ( ! is_int( $deepl_chars_used ) || $deepl_chars_used < 0 ) {
			$deepl_chars_used = 0;
		}
		$deepl_chars_used                            += mb_strlen( $original_singular );
		$gp_external_translations['deepl_chars_used'] = $deepl_chars_used;
		update_user_option( get_current_user_id(), 'gp_external_translations', $gp_external_translations );
	}

	/**
	 * Updates the number of tokens used by OpenAI.
	 *
	 * @param int $tokens_used The number of tokens used.
	 */
	private function update_openai_tokens_used( int $tokens_used ) {
		$gp_external_translations = get_user_option( 'gp_external_translations' );
		$openai_tokens_used       = gp_array_get( $gp_external_translations, 'openai_tokens_used' );
		if ( ! is_int( $openai_tokens_used ) || $openai_tokens_used < 0 ) {
			$openai_tokens_used = 0;
		}
		$openai_tokens_used                            += $tokens_used;
		$gp_external_translations['openai_tokens_used'] = $openai_tokens_used;
		update_user_option( get_current_user_id(), 'gp_external_translations', $gp_external_translations );
	}

	/**
	 * Check if a string should be pretranslated.
	 *
	 * @param int                $original_id     The original ID.
	 * @param GP_Translation_Set $translation_set The translation set.
	 *
	 * @return false|GP_Thing
	 */
	private function should_pretranslate( $original_id, $translation_set ) {
		$original = GP::$original->get( $original_id );
		if ( ! $original ) {
			return false;
		}
		if ( ! is_null( $original->plural ) ) {
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
		return $original;
	}

	private function get_suggestion_from_tm( $original, $locale ) {
		$suggestions = Translation_Memory_Client::query( $original->singular, $locale->slug );
		if ( empty( $suggestions ) ) {
			return false;
		}
		if ( is_wp_error( $suggestions ) ) {
			return false;
		}
		if ( $suggestions[0]['similarity_score'] < 1 ) {
			return false;
		}
		return $suggestions[0]['translation'];
	}

	private function get_suggestion_from_openai( $original, $locale ) {
		$current_set_slug                = 'default';

		$locale_glossary_translation_set = GP::$translation_set->by_project_id_slug_and_locale( 0, $current_set_slug, $locale->slug );
		$locale_glossary                 = GP::$glossary->by_set_id( $locale_glossary_translation_set->id );

		$openai_query    = '';
		$glossary_query  = '';
		$gp_default_sort = get_user_option( 'gp_default_sort' );
		$openai_key      = gp_array_get( $gp_default_sort, 'openai_api_key' );
		if ( empty( trim( $openai_key ) ) ) {
			return array();
		}
		$openai_prompt      = gp_array_get( $gp_default_sort, 'openai_custom_prompt' );
		$openai_temperature = gp_array_get( $gp_default_sort, 'openai_temperature', 0 );
		if ( ! is_float( $openai_temperature ) || $openai_temperature < 0 || $openai_temperature > 2 ) {
			$openai_temperature = 0;
		}

		$glossary_entries = array();
		foreach ( $locale_glossary->get_entries() as $gp_glossary_entry ) {
			if ( strpos( strtolower( $original->singular ), strtolower( $gp_glossary_entry->term ) ) !== false ) {
				// Use the translation as key, because we could have multiple translations with the same term.
				$glossary_entries[ $gp_glossary_entry->translation ] = $gp_glossary_entry->term;
			}
		}
		if ( ! empty( $glossary_entries ) ) {
			$glossary_query = ' The following terms are translated as follows: ';
			foreach ( $glossary_entries as $translation => $term ) {
				$glossary_query .= '"' . $term . '" is translated as "' . $translation . '"';
				if ( array_key_last( $glossary_entries ) != $translation ) {
					$glossary_query .= ', ';
				}
			}
			$glossary_query .= '.';
		}

		$openai_query .= ' Translate the following text to ' . $locale->english_name . ": \n";
		$openai_query .= '"' . $original->singular . '"';
		$openai_model  = gp_array_get( $gp_default_sort, 'openai_model', 'gpt-3.5-turbo' );

		$messages = array(
			array(
				'role'    => 'system',
				'content' => $openai_prompt . $glossary_query,
			),
			array(
				'role'    => 'user',
				'content' => $openai_query,
			),
		);
		$openai_response = wp_remote_post(
			'https://api.openai.com/v1/chat/completions',
			array(
				'timeout' => 20,
				'headers' => array(
					'Content-Type'  => 'application/json',
					'Authorization' => 'Bearer ' . $openai_key,
				),
				'body'    => wp_json_encode(
					array(
						'model'       => $openai_model,
						'max_tokens'  => 1000,
						'n'           => 1,
						'messages'    => $messages,
						'temperature' => $openai_temperature,
					)
				),
			)
		);
		if ( is_wp_error( $openai_response ) ) {
			return false;
		}
		$response_status = wp_remote_retrieve_response_code( $openai_response );
		if ( 200 !== $response_status ) {
			return false;
		}

		return json_decode( wp_remote_retrieve_body( $openai_response ), true );
	}

	private function get_suggestion_from_deepl( $original, $locale ) {
		$gp_default_sort = get_user_option( 'gp_default_sort' );
		$deepl_api_key   = gp_array_get( $gp_default_sort, 'deepl_api_key' );
		$deepl_url_free  = 'https://api-free.deepl.com/v2/translate';
		$deepl_url_pro   = 'https://api.deepl.com/v2/translate';
		$deepl_url       = gp_array_get( $gp_default_sort, 'deepl_use_api_pro', false ) ? $deepl_url_pro : $deepl_url_free;
		if ( empty( trim( $deepl_api_key ) ) ) {
			return false;
		}
		$target_lang = $this->get_deepl_locale( $locale->slug );
		error_log( print_r( $locale, true ) );
		if ( empty( $target_lang ) ) {
			return false;
		}

		$deepl_response = wp_remote_post(
			$deepl_url,
			array(
				'timeout' => 20,
				'body'    => array(
					'auth_key'    => $deepl_api_key,
					'text'        => $original->singular,
					'source_lang' => 'EN',
					'target_lang' => $target_lang,
					'formality'   => $this->get_language_formality( $target_lang, $locale->slug ),
				),
			),
		);
		if ( is_wp_error( $deepl_response ) ) {
			return false;
		}
		$response_status = wp_remote_retrieve_response_code( $deepl_response );
		if ( 200 !== $response_status ) {
			return false;
		}
		$body          = wp_remote_retrieve_body( $deepl_response );
		return json_decode( $body )->translations[0]->text;
	}

	private function store_translation( $original_id, $translation_set_id, $translation_0, $current_user_id ) {
		return GP::$translation->create(
			array(
				'original_id'        => $original_id,
				'translation_set_id' => $translation_set_id,
				'translation_0'      => $translation_0,
				'status'             => 'waiting',
				'user_id'            => $current_user_id,
			)
		);
	}

	private function set_notice( $pretranslations_added ) {
		$notice = sprintf(
		/* translators: %s: Pretranslations count. */
			_n( '%s pretranslation was added', '%s pretranslations were added', $pretranslations_added, 'glotpress' ),
			$pretranslations_added
		);
		gp_notice_set( $notice );
	}
}

add_action( 'gp_init', array( 'GP_Bulk_Pretranslations', 'init' ) );