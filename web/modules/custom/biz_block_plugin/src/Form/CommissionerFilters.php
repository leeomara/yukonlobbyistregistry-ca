<?php

namespace Drupal\biz_block_plugin\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\TempStore\PrivateTempStoreFactory;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\ReplaceCommand;

/**
 * Provides the Commissioner activity filter form.
 * 
 * This form is rendered as a block on the Commissioner activities page
 * and allows filtering activity records retrieved from the backend API.
 * The form uses AJAX to update the activities table without a full page reload.
 */
class CommissionerFilters extends FormBase {

  /**
   * {@inheritdoc}
   */
  protected $tempStore;
	
	public function __construct(PrivateTempStoreFactory $temp_store_factory) {
	  $this->tempStore = $temp_store_factory->get('form_commissioner_filters');
	}
	
	public static function create(ContainerInterface $container) {
	  return new static(
	    $container->get('tempstore.private')
	  );
	}

  /**
   * Returns a unique string identifying the form.
   *
   * The returned ID should be a unique string that can be a valid PHP function
   * name, since it's used in hook implementation names such as
   * hook_form_FORM_ID_alter().
   *
   * @return string
   *   The unique string identifying the form.
   */
  public function getFormId() {
    return 'form_commissioner_filter';
  }

  /**
   * Form constructor.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   *
   * @return array
   *   The form structure.
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $params = $this->tempStore->get('params') ?? [];
		
		$form['quick_filters'] = [
		  '#type' => 'container',
		  '#attributes' => ['class' => ['quick-filters']],
		];
		$form['quick_filters']['org_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Organization/Name'),
      '#default_value' => $params['org_name'] ?? '',
      '#description' => $this->t('Matches first name, last name, legal organization, or consultant organization name.'),
    ];
		$form['quick_filters']['front_status'] = [
      '#type' => 'select',
      '#title' => $this->t('Public status'),
      '#options' => [
        '' => $this->t('All'),
        'Active' => $this->t('Active'),
        'Inactive' => $this->t('Inactive'),
        'Non-compliant' => $this->t('Non-compliant'),
      ],
      '#default_value' =>  $params['front_status']  ?? '',
      '#description' => $this->t('Select the public status of activities'),

    ];
		$form['quick_filters']['backend_status'] = [
      '#type' => 'select',
      '#title' => $this->t('Backend status'),
      '#options' => [
        '' => $this->t('All'),
        'Active' => $this->t('Active'),
        'Pending' => $this->t('Pending'),
        'Non-compliant' => $this->t('Non-compliant'),
        'About to be Not in compliance' => $this->t('About to be Not in compliance'),
        'Inactive' => $this->t('Inactive'),
        'Completed' => $this->t('Completed'),
      ],
      '#default_value' => $params['backend_status'] ??  '',
      '#description' => $this->t('Select the backend status of activities'),
    ];

		$form['advanced'] = [
		  '#type' => 'details',
		  '#title' => $this->t('More filters'),
		  '#open' => !empty(array_filter($params)),
		  '#attributes' => ['class' => ['filters-accordion']],
		];
		
		$form['advanced']['lobbyist_type'] = [
      '#type' => 'select',
      '#title' => $this->t('Type'),
      '#options' => [
        '' => $this->t('All'),
        'in_house_lobbyist' => $this->t('In-house'),
        'consultant_lobbyist' => $this->t('Consultant'),
      ],
      '#default_value' => $params['lobbyist_type'] ?? '',

    ];
    
    $form['advanced']['inline_fields'] = [
			'#type' => 'container',
			'#attributes' => [
			'class' => ['inline-fields'],
			],
		];

    $form['advanced']['inline_fields']['act_title'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Title'),
      '#default_value' => $params['act_title'] ?? '',
    ];
		
		
    $form['advanced']['inline_fields']['user_email_filter'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Email'),
      '#default_value' => $params['user_email_filter'] ?? '',
    ];

    // Group submit handlers in an actions element with a key of "actions" so
    // that it gets styled correctly, and so that other modules may add actions
    // to the form. This is not required, but is convention.
    $form['quick_filters']['actions'] = [
      '#type' => 'actions',
      '#attributes' => ['class' => ['display-inline']],
    ];

		$form['quick_filters']['actions']['submit'] = [
		  '#type' => 'button',
		  '#name' => 'filter',
		  '#value' => $this->t('Filter'),
		  '#ajax' => [
		    'callback' => '::ajaxSubmit',
		    'wrapper' => 'commissioner-activities-wrapper',
		  ],
		];
		
		$form['quick_filters']['actions']['reset'] = [
		  '#type' => 'button',
		  '#name' => 'reset',
		  '#value' => $this->t('Reset'),
		  '#ajax' => [
		    'callback' => '::ajaxSubmit',
		    'wrapper' => 'commissioner-activities-wrapper',
		  ],
		];
    $form['#prefix'] = '<div id="commissioner-filters-wrapper">';
		$form['#suffix'] = '</div>';
		$form['#attached']['library'][] = 'core/drupal.ajax';
    return $form;
  }
  
public function ajaxSubmit(array &$form, FormStateInterface $form_state) {
  try {

    $this->submitForm($form, $form_state);
    $form_state->setRebuild(TRUE);
    $response = new AjaxResponse();
    $trigger = $form_state->getTriggeringElement();
    $renderer = \Drupal::service('renderer');

    if (!empty($trigger['#name']) && $trigger['#name'] === 'reset') {
      $this->tempStore->delete('params');
		  $form_state->setUserInput([]);
		  $form_state->setValues([]);
		  $form_state->setRebuild(TRUE);
      $form = \Drupal::formBuilder()->rebuildForm(
			  $this->getFormId(),
			  $form_state,
			  $form
			);
			$form_rendered = $renderer->renderRoot($form);

      $response->addCommand(new ReplaceCommand(
	      '#commissioner-filters-wrapper',
	      $form_rendered
	    ));
    }
    $content = $this->buildTable();
    $content_rendered = $renderer->renderRoot($content);
    
    $response->addCommand(new ReplaceCommand(
      '#commissioner-activities-wrapper',
      $content_rendered
    ));

    return $response;
    
  }
  catch (\Exception $e) {
    \Drupal::logger('ajax')->error($e->getMessage());
  }
}


  /**
   *
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
	  $values = $form_state->cleanValues()->getValues();
	  $params = [
		  'org_name' => $values['org_name'] ?? '',
		  'act_title' => $values['act_title'] ?? '',
		  'front_status' => $values['front_status'] ?? '',
		  'backend_status' => $values['backend_status'] ?? '',
		  'lobbyist_type' => $values['lobbyist_type'] ?? '',
		  'user_email_filter' => $values['user_email_filter'] ?? '',
		];

	  $this->tempStore->set('params', $params);
  }
  
  public function buildTable() {
	  $tempstore = \Drupal::service('tempstore.private')->get('form_commissioner_filters');
	  $params = $tempstore->get('params') ?? [];
	
	  $header_endpoint = \Drupal::config('biz_block_plugin.settings')
	    ->get('header_commissioner_activities');
	
	  $activities_endpoint = \Drupal::config('biz_block_plugin.settings')
	    ->get('commissioner_activities');
	
	  $header_response = \Drupal\biz_block_plugin\Controller\GeneralFunctions::getHeadersTable($header_endpoint, 'header-orange');
	  $activities_response = \Drupal\biz_block_plugin\Controller\GeneralFunctions::getAllData($activities_endpoint, TRUE, $params);
	  $header = $header_response['header'] ?? [];
	  $rows = [];
	  if ($header_response && $activities_response) {
	    $rows_response = json_decode($activities_response['message']);
	    $rows = \Drupal\biz_block_plugin\Controller\GeneralFunctions::generateTableRows($header_response, $rows_response);
	  }
	
	  return [
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
	      '#header' => $header ,
	      '#rows' => $rows,
	      '#empty' => "Currently you don’t have activities",
	      '#caption' => t('Activities'),
	      '#attributes' => ['id' => 'commissioner-activities','class' => ['table-orange-header', 'general-lobbyist-tables'],],
	       'drupalSettings' => [
		      'biz_block_plugin' => [
		        'id' => "commissioner-activities",
		        'url_api' => '',
		        'sort_column' => 0,
		        'sort_order' => 'desc',
		      ],
		    ],
	    ],
	  ];
	}


}
