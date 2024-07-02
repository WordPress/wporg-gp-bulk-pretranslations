<?php

namespace WordPressdotorg\GlotPress\Bulk_Pretranslations;

use GP;
use GP_Locale;
use GP_Project;
use GP_Route;
use GP_Translation;
use GP_Translation_Set;

class Plugin extends GP_Route {

	private static $instance = null;

	public static function get_instance() {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	public function __construct() {
		parent::__construct();
		add_action( 'gp_translation_set_bulk_action', array( $this, 'add_pretranslation_options' ) );
        add_action( 'gp_translation_set_bulk_action_post', array( $this, 'store_pretanslations' ), 10, 4 );
    }

    /**
     * Add pre-translation options to the bulk actions dropdown.
     *
     * It adds it only for the GTE and translation memory, OpenAI and DeepL.
     *
     * @param GP_Translation_Set $translation_set The translation set.
     */
    public function add_pretranslation_options( GP_Translation_Set $translation_set ):void {
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
		$deepl = new DeepL();
        if ( $deepl_api_key && $deepl->get_deepl_locale( $locale ) ) {
            echo '<option value="bulk-pretranslation-deepl">' . esc_html__( 'DeepL', 'glotpress' ) . '</option>';
        }
        echo '</optgroup>';
    }

    /**
     * Get the suggestions and store the pre-translations for the selected rows.
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

	    $current_user_id       = get_current_user_id();
	    $pretranslations_added = 0;
	    foreach ( $bulk['row-ids'] as $original_id ) {
		    $translation_0= null;
		    if ( 'bulk-pretranslation-tm' === $bulk['action'] ) {
			    $tm            = new Translation_Memory();
			    $translation_0 = $tm->get_suggestion_0( $original_id, $locale, $translation_set );
		    }
		    if ( 'bulk-pretranslation-openai' === $bulk['action'] ) {
			    $openai        = new OpenAI();
			    $translation_0 = $openai->get_suggestion_0( $original_id, $locale, $translation_set );
			    $openai->update_openai_tokens_used();
		    }
		    if ( 'bulk-pretranslation-deepl' === $bulk['action'] ) {
			    $deepl         = new DeepL();
			    $translation_0 = $deepl->get_suggestion_0( $original_id, $locale, $translation_set );
			    $deepl->update_deepl_chars_used();
		    }
		    if ( $translation_0 ) {
			    $translation_created = $this->store_pretranslation( $original_id, $translation_set->id, $translation_0, $current_user_id );
			    if ( $translation_created ) {
				    $pretranslations_added ++;
			    }
		    }
	    }
	    $this->set_notice( $pretranslations_added );
    }

	/**
	 * Store the pre-translation.
	 *
	 * @param int    $original_id        The original ID.
	 * @param int    $translation_set_id The translation set ID.
	 * @param string $translation_0      The translation.
	 * @param int    $current_user_id    The current user ID.
	 *
	 * @return false|GP_Translation
	 */
	private function store_pretranslation( int $original_id, int $translation_set_id, string $translation_0, int $current_user_id ) {
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

	/**
	 * Set the notice with the number of pre-translations added.
	 *
	 * @param int $pretranslations_added The number of pre-translations added.
	 */
	private function set_notice( int $pretranslations_added ):void {
		$notice = sprintf(
		/* translators: %s: Pretranslations count. */
			_n( '%s pretranslation was added', '%s pretranslations were added', $pretranslations_added, 'glotpress' ),
			$pretranslations_added
		);
		gp_notice_set( $notice );
	}
}