<?php

/**
 * @file
 * Definition of Drupal\views\Tests\ModuleTest.
 */

namespace Drupal\views\Tests;

/**
 * Tests basic functions from the Views module.
 */
class ModuleTest extends ViewTestBase {

  public static function getInfo() {
    return array(
      'name' => 'Views Module tests',
      'description' => 'Tests some basic functions of views.module.',
      'group' => 'Views',
    );
  }

  public function setUp() {
    parent::setUp();

    $this->enableViewsTestModule();
    drupal_theme_rebuild();
  }

  public function viewsData() {
    $data = parent::viewsData();
    $data['views_test_data_previous'] = array();
    $data['views_test_data_previous']['id']['field']['moved to'] = array('views_test_data', 'id');
    $data['views_test_data_previous']['id']['filter']['moved to'] = array('views_test_data', 'id');
    $data['views_test_data']['age_previous']['field']['moved to'] = array('views_test_data', 'age');
    $data['views_test_data']['age_previous']['sort']['moved to'] = array('views_test_data', 'age');
    $data['views_test_data_previous']['name_previous']['field']['moved to'] = array('views_test_data', 'name');
    $data['views_test_data_previous']['name_previous']['argument']['moved to'] = array('views_test_data', 'name');

    return $data;
  }

  public function test_views_trim_text() {
    // Test unicode, @see http://drupal.org/node/513396#comment-2839416
    $text = array(
      'Tuy nhiên, những hi vọng',
      'Giả sử chúng tôi có 3 Apple',
      'siêu nhỏ này là bộ xử lý',
      'Di động của nhà sản xuất Phần Lan',
      'khoảng cách từ đại lí đến',
      'của hãng bao gồm ba dòng',
      'сд асд асд ас',
      'асд асд асд ас'
    );
    // Just test maxlength without word boundry.
    $alter = array(
      'max_length' => 10,
    );
    $expect = array(
      'Tuy nhiên,',
      'Giả sử chú',
      'siêu nhỏ n',
      'Di động củ',
      'khoảng các',
      'của hãng b',
      'сд асд асд',
      'асд асд ас',
    );

    foreach ($text as $key => $line) {
      $result_text = views_trim_text($alter, $line);
      $this->assertEqual($result_text, $expect[$key]);
    }

    // Test also word_boundary
    $alter['word_boundary'] = TRUE;
    $expect = array(
      'Tuy nhiên',
      'Giả sử',
      'siêu nhỏ',
      'Di động',
      'khoảng',
      'của hãng',
      'сд асд',
      'асд асд',
    );

    foreach ($text as $key => $line) {
      $result_text = views_trim_text($alter, $line);
      $this->assertEqual($result_text, $expect[$key]);
    }
  }

  /**
   * Tests the views_get_handler method.
   */
  function testviews_get_handler() {
    $types = array('field', 'area', 'filter');
    foreach ($types as $type) {
      $handler = views_get_handler($this->randomName(), $this->randomName(), $type);
      $this->assertEqual('Drupal\views\Plugin\views\\' . $type . '\Broken', get_class($handler), t('Make sure that a broken handler of type: @type are created', array('@type' => $type)));
    }

    $views_data = $this->viewsData();
    $test_tables = array('views_test_data' => array('id', 'name'));
    foreach ($test_tables as $table => $fields) {
      foreach ($fields as $field) {
        $data = $views_data[$table][$field];
        foreach ($data as $id => $field_data) {
          if (!in_array($id, array('title', 'help'))) {
            $handler = views_get_handler($table, $field, $id);
            $this->assertInstanceHandler($handler, $table, $field, $id);
          }
        }
      }
    }

    // Test the automatic conversion feature.

    // Test the automatic table renaming.
    $handler = views_get_handler('views_test_data_previous', 'id', 'field');
    $this->assertInstanceHandler($handler, 'views_test_data', 'id', 'field');
    $handler = views_get_handler('views_test_data_previous', 'id', 'filter');
    $this->assertInstanceHandler($handler, 'views_test_data', 'id', 'filter');

    // Test the automatic field renaming.
    $handler = views_get_handler('views_test_data', 'age_previous', 'field');
    $this->assertInstanceHandler($handler, 'views_test_data', 'age', 'field');
    $handler = views_get_handler('views_test_data', 'age_previous', 'sort');
    $this->assertInstanceHandler($handler, 'views_test_data', 'age', 'sort');

    // Test the automatic table and field renaming.
    $handler = views_get_handler('views_test_data_previous', 'name_previous', 'field');
    $this->assertInstanceHandler($handler, 'views_test_data', 'name', 'field');
    $handler = views_get_handler('views_test_data_previous', 'name_previous', 'argument');
    $this->assertInstanceHandler($handler, 'views_test_data', 'name', 'argument');

    // Test the override handler feature.
    $handler = views_get_handler('views_test_data', 'job', 'filter', 'string');
    $this->assertEqual('Drupal\\views\\Plugin\\views\\filter\\String', get_class($handler));
  }

  /**
   * Tests the load wrapper/helper functions.
   */
  public function testLoadFunctions() {
    $controller = entity_get_controller('view');

    // Test views_view_is_enabled/disabled.
    $load = $controller->load(array('archive'));
    $archive = reset($load);
    $this->assertTrue(views_view_is_disabled($archive), 'views_view_is_disabled works as expected.');
    // Enable the view and check this.
    $archive->enable();
    $this->assertTrue(views_view_is_enabled($archive), ' views_view_is_enabled works as expected.');

    // We can store this now, as we have enabled/disabled above.
    $all_views = $controller->load();

    // Test views_get_all_views().
    $this->assertIdentical(array_keys($all_views), array_keys(views_get_all_views()), 'views_get_all_views works as expected.');

    // Test views_get_enabled_views().
    $expected_enabled = array_filter($all_views, function($view) {
      return views_view_is_enabled($view);
    });
    $this->assertIdentical(array_keys($expected_enabled), array_keys(views_get_enabled_views()), 'Expected enabled views returned.');

    // Test views_get_disabled_views().
    $expected_disabled = array_filter($all_views, function($view) {
      return views_view_is_disabled($view);
    });
    $this->assertIdentical(array_keys($expected_disabled), array_keys(views_get_disabled_views()), 'Expected disabled views returned.');

    // Test views_get_views_as_options().
    // Test the $views_only parameter.
    $this->assertIdentical(array_keys($all_views), array_keys(views_get_views_as_options(TRUE)), 'Expected option keys for all views were returned.');
    $expected_options = array();
    foreach ($all_views as $id => $view) {
      $expected_options[$id] = $view->getHumanName();
    }
    $this->assertIdentical($expected_options, views_get_views_as_options(TRUE), 'Expected options array was returned.');

    // Test the default.
    $this->assertIdentical($this->formatViewOptions($all_views), views_get_views_as_options(), 'Expected options array for all views was returned.');
    // Test enabled views.
    $this->assertIdentical($this->formatViewOptions($expected_enabled), views_get_views_as_options(FALSE, 'enabled'), 'Expected enabled options array was returned.');
    // Test disabled views.
    $this->assertIdentical($this->formatViewOptions($expected_disabled), views_get_views_as_options(FALSE, 'disabled'), 'Expected disabled options array was returned.');

    // Test the sort parameter.
    $all_views_sorted = $all_views;
    ksort($all_views_sorted);
    $this->assertIdentical(array_keys($all_views_sorted), array_keys(views_get_views_as_options(TRUE, 'all', NULL, FALSE, TRUE)), 'All view id keys returned in expected sort order');

    // Test $exclude_view parameter.
    $this->assertFalse(array_key_exists('archive', views_get_views_as_options(TRUE, 'all', 'archive')), 'View excluded from options based on name');
    $this->assertFalse(array_key_exists('archive:default', views_get_views_as_options(FALSE, 'all', 'archive:default')), 'View display excluded from options based on name');
    $this->assertFalse(array_key_exists('archive', views_get_views_as_options(TRUE, 'all', $archive->getExecutable())), 'View excluded from options based on object');

    // Test the $opt_group parameter.
    $expected_opt_groups = array();
    foreach ($all_views as $id => $view) {
      foreach ($view->display as $display_id => $display) {
          $expected_opt_groups[$view->id()][$view->id() . ':' . $display['id']] = t('@view : @display', array('@view' => $view->id(), '@display' => $display['id']));
      }
    }
    $this->assertIdentical($expected_opt_groups, views_get_views_as_options(FALSE, 'all', NULL, TRUE), 'Expected option array for an option group returned.');
  }

  /**
   * Helper to return an expected views option array.
   *
   * @param array $views
   *   An array of Drupal\views\ViewStorage objects to create an options array
   *   for.
   *
   * @return array
   *   A formatted options array that matches the expected output.
   */
  protected function formatViewOptions(array $views = array()) {
    $expected_options = array();
    foreach ($views as $id => $view) {
      foreach ($view->display as $display_id => $display) {
        $expected_options[$view->id() . ':' . $display['id']] = t('View: @view - Display: @display',
          array('@view' => $view->name, '@display' => $display['id']));
      }
    }

    return $expected_options;
  }

  /**
   * Ensure that a certain handler is a instance of a certain table/field.
   */
  function assertInstanceHandler($handler, $table, $field, $id) {
    $table_data = views_fetch_data($table);
    $field_data = $table_data[$field][$id];

    $this->assertEqual($field_data['id'], $handler->getPluginId());
  }

}
