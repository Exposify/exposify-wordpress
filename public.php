<?php

class ExposifyViewer {

  /**
  * An instance of the Exposify handler.
  *
  * @var Exposify
  */
  public $exposify;

  /**
  * Construct the class.
  *
  * @param  string  $apiKey
  * @param  string  $baseUrl
  * @return void
  */
  public function __construct($apiKey, $baseUrl = 'https://app.exposify.de')
  {
    $this->exposify = new Exposify($apiKey, $baseUrl);

    add_filter('the_content',        [$this, 'changePageContent']);
    add_filter('page_template',      [$this, 'changePageTemplate']);
    add_action('wp_enqueue_scripts', [$this, 'insertLinks']);
    // add_filter('wp_title',           [$this, 'changePageTitle']);
    // add_filter('the_title',              [$this, 'changePageTitle']);
    add_filter('pre_get_document_title', [$this, 'changeSiteTitle'], 10);
  }

  /**
  * Request the property/properties, if there isn't a result yet.
  *
  * @return void
  */
  public function attemptRequest()
  {
    if (empty($this->exposify->html->getResult())) {
      if (get_query_var('slug')) {
        $this->exposify->html->requestSingleProperty(get_query_var('slug'));
      } else {
        $this->exposify->html->requestAllProperties(get_query_var('search', ''));
      }
    }
  }

  /**
  * Change the page template to the specified one.
  *
  * @param  string  $oldTemplate
  * @return string
  */
  public function changePageTemplate($oldTemplate)
  {
    if (get_the_ID() != get_option('exposify_properties_page_id')) {
      return $oldTemplate;
    }

    $new_template = get_option('exposify_settings')['exposify_theme_template'];
    if (locate_template($new_template) != '' && $new_template != 'default') {
      return get_template_directory() . '/' . $new_template;
    }

    return $oldTemplate;
  }

  /**
  * Insert the properties into the page.
  *
  * @param  string  $oldContent
  * @return string
  */
  public function changePageContent($oldContent)
  {
    if (get_the_ID() != get_option('exposify_properties_page_id')) {
      return $oldContent;
    }
    $this->attemptRequest();

    return $this->exposify->html->getContent();
  }

  /**
  * Change the page title to the property name.
  *
  * @param  string  $oldTitle
  * @return string
  */
  public function changePageTitle($oldTitle)
  {
    if (!get_query_var('slug')) {
      return $oldTitle;
    }

    if (get_the_ID() != get_option('exposify_properties_page_id')) {
      return $oldTitle;
    }

    if (!in_the_loop()) {
      return $oldTitle;
    }

    $this->attemptRequest();

    return $this->exposify->html->getTitle();
  }

  /**
  * Change the site title to the property name.
  *
  * @param  string  $oldTitle
  * @return string
  */
  public function changeSiteTitle($oldTitle)
  {
    if (get_query_var('slug')) {
      $this->attemptRequest();
      return $this->exposify->html->getTitle();
    }

    return $oldTitle;
  }

  /**
  * Insert all external CSS and JS files in the page.
  *
  * @return void
  */
  public function insertLinks()
  {
    if (get_the_ID() != get_option('exposify_properties_page_id')) {
      return;
    }

    $this->attemptRequest();

    if (isset($this->exposify->html->getError()['attributes']['css']))  {
      $css = $this->exposify->html->getError()['attributes']['css'];
    }
    if (isset($this->exposify->html->getResult()['attributes']['css'])) {
      $css = $this->exposify->html->getResult()['attributes']['css'];
    }
    if (isset($css) && is_array($css)) {
      $i = 1;
      foreach ($css as $css_src) {
        wp_enqueue_style('exposify-' . $i, $css_src);
        $i++;
      }
    }

    if (isset($this->exposify->html->getError()['attributes']['js']))  {
      $js = $this->exposify->html->getError()['attributes']['js'];
    }
    if (isset($this->exposify->html->getResult()['attributes']['js'])) {
      $js = $this->exposify->html->getResult()['attributes']['js'];
    }

    if (isset($js) && is_array($js)) {
      $i = 1;
      foreach ($js as $js_src) {
        wp_enqueue_script('exposify-' . $i, $js_src, ['jquery'], false, true);
        $i++;
      }
    }
  }
}

$viewer = new ExposifyViewer(get_option('exposify_settings')['exposify_api_key']);
