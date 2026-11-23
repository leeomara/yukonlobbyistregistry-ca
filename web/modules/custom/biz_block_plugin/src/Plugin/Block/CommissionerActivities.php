<?php

namespace Drupal\biz_block_plugin\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Block\BlockPluginInterface;
use Drupal\biz_block_plugin\Controller\GeneralFunctions;

/**
 * Provides a custom block.
 *
 * @Block(
 *   id = "commissioner_act_block",
 *   admin_label = @Translation("Commissioner Activities block"),
 *   category = @Translation("Bizont custom block")
 * )
 */
class CommissionerActivities extends BlockBase implements BlockPluginInterface {

  /**
   * Display all activities.
   */
  public function build() {
    $language = \Drupal::languageManager()->getCurrentLanguage()->getId();
    $current_user = \Drupal::currentUser();
    $email = $current_user->getEmail();
    $roles = $current_user->getRoles();
    $base_url = \Drupal::config('biz_lobbyist_registration.settings')->get('base_url');
    $content = [];
    $tempstore = \Drupal::service('tempstore.private')->get('form_commissioner_filters');
    $params = $tempstore->get('params') ?? [];

    if (in_array("role_administrator", $roles)) {
		$content['wrapper'] = [
			'#type' => 'container',
			'#attributes' => ['id' => 'commissioner-main-wrapper'],
		];
  		$content['wrapper']['commissioner_wrapper'] = [
			'#type' => 'container',
			'#attributes' => ['id' => 'commissioner-wrapper'],
		];
		// FORM
		$content['wrapper']['commissioner_wrapper']['filters'] = \Drupal::formBuilder()
			->getForm('Drupal\biz_block_plugin\Form\CommissionerFilters');

		// MODAL
		$content['wrapper']['commissioner_wrapper']['modal'] = [
			'#theme' => 'modal_confirmation',
			'#title' => t('Accept'),
			'#label_yes' => t('Accept'),
			'#label_cancel' => t('Cancel'),
			'#body_message' => t('Are you sure you want to accept this activity?'),
		];

		$header_activities_endpoint = \Drupal::config('biz_block_plugin.settings')->get('header_commissioner_activities');
		$activities_endpoint = \Drupal::config('biz_block_plugin.settings')->get('commissioner_activities');
		$header_response = GeneralFunctions::getHeadersTable($header_activities_endpoint, 'header-orange');
		$activities_response = GeneralFunctions::getAllData($activities_endpoint, TRUE, $params);
		$activity_rows = [];
		$activity_header = $header_response['header'] ?? [];
		if ($header_response && $activities_response) {
			$rows_response = json_decode($activities_response['message']);
			$activity_rows = GeneralFunctions::generateTableRows($header_response, $rows_response);
		}

		$content['wrapper']['commissioner_wrapper']['table_wrapper'] = [
			'#type' => 'container',
			'#attributes' => ['id' => 'commissioner-activities-wrapper'],
			'#attached' => [
			'library' => [
				'biz_block_plugin/biz-block-plugin',
			],
			'drupalSettings' => [
				'biz_block_plugin' => [
				'id' => 'commissioner-activities',
				'url_api' => '',
				'sort_column' => 0,
				'sort_order' => 'desc',
				],
			],
			],
			'table' => [
			'#theme' => 'custom_table',
			'#header' => $activity_header,
			'#rows' => $activity_rows,
			'#empty' => "Currently you don’t have activities",
			'#caption' => t('Activities'),
			'#attributes' => [
				'id' => 'commissioner-activities',
				'class' => ['table-orange-header', 'general-lobbyist-tables'],
			],
			],
		];
    }
    return $content;
  }

  /**
   * Disable caching for this block.
   */
  public function getCacheMaxAge() {
    return 0;
  }

}
