// Shared Elementor edit plan (used by step B and the local test).
$EL_PLAN = [
  // header templates: drop the Studio button column, 2 columns at 50%
  58  => ['drop' => ['1dda1e9'], 'text' => []],
  261 => ['drop' => ['abdc780'], 'text' => []],
  266 => ['drop' => ['96da73c'], 'text' => []],
  9   => ['drop' => ['99cb5fd'], 'text' => [
    '39294aef' => ['editor', 'We have three apartments in Zermatt, split over two locations.', 'We have two apartments in Zermatt, in two separate locations.'],
    '182fc37b' => ['editor', 'these 3 apartments', 'these apartments']]],
  195 => ['drop' => ['e1dab86'], 'text' => [
    '44ed5e6'  => ['editor', 'Wir haben drei Wohnungen in Zermatt.', 'Wir haben zwei Wohnungen in Zermatt.'],
    '1ed8cb86' => ['editor', 'diese 3 Wohnungen', 'diese Wohnungen']]],
  196 => ['drop' => ['f3e7f71'], 'text' => [
    'f1c02b3'  => ['editor', 'Nous avons trois appartements à Zermatt, répartis sur deux sites.', 'Nous avons deux appartements à Zermatt, sur deux sites différents.'],
    '74d7b452' => ['editor', 'ces 3 appartements', 'ces appartements']]],
  148 => ['drop' => ['681e62f3'], 'text' => [
    '12cc7f5a' => ['title', 'has three apartments', 'has two apartments']]],
  1177 => ['drop' => ['ff47e36'], 'text' => [
    '12cc7f5a' => ['title', 'dispose de trois appartements', 'dispose de deux appartements']]],
  1186 => ['drop' => ['5f30b0a'], 'text' => [
    '12cc7f5a' => ['title', 'drei Ferienwohnungen', 'zwei Ferienwohnungen']]],
];
// Returns [newElements, notes]; throws if anything expected is missing.
$el_transform = function (array $els, array $plan) {
  $notes = [];
  $dropped = 0; $texts = 0;
  $walk = function (array $els) use (&$walk, $plan, &$dropped, &$texts, &$notes) {
    $ids = array_column($els, 'id');
    $hit = array_intersect($ids, $plan['drop']);
    if ($hit) {
      $els = array_values(array_filter($els, function ($e) use ($plan) { return !in_array($e['id'], $plan['drop'], true); }));
      $n = count($els);
      if ($n !== 2) throw new Exception('expected 2 columns left, got ' . $n);
      foreach ($els as &$c) { $c['settings']['_column_size'] = 50; $c['settings']['_inline_size'] = null; }
      unset($c);
      $dropped += count($hit); $notes[] = 'dropped ' . implode(',', $hit);
    }
    foreach ($els as &$e) {
      if (isset($plan['text'][$e['id']])) {
        [$key, $from, $to] = $plan['text'][$e['id']];
        $cnt = 0; $e['settings'][$key] = str_replace($from, $to, $e['settings'][$key] ?? '', $cnt);
        if ($cnt !== 1) throw new Exception("text in {$e['id']} replaced $cnt times");
        $texts++;
      }
      if (!empty($e['elements'])) {
        $before = count($e['elements']);
        $e['elements'] = $walk($e['elements']);
        if ($before === 3 && count($e['elements']) === 2 && ($e['settings']['structure'] ?? '') === '30') $e['settings']['structure'] = '20';
      }
    }
    unset($e);
    return $els;
  };
  $out = $walk($els);
  if ($dropped !== count($plan['drop'])) throw new Exception("dropped $dropped of " . count($plan['drop']));
  if ($texts !== count($plan['text'])) throw new Exception("texts $texts of " . count($plan['text']));
  return [$out, $notes];
};

// ---- Step B: remove Haus Imperial Studio ----
global $wpdb; $P = $wpdb->prefix;
wp_set_current_user(4);
$up = wp_upload_dir(); $B = $up['basedir'];
@unlink("$B/zz-probe-7f3k2.json"); @unlink("$B/zz-step-a-9q4m.json");
$log = []; $backup = [];
$step = function ($name, $fn) use (&$log) { try { $log[$name] = $fn(); } catch (Throwable $e) { $log[$name] = 'ERROR: ' . $e->getMessage() . ' @' . $e->getLine(); } };

// 1. Redirects (Rank Math), 301, exact match
$step('redirects', function () use ($wpdb, $P) {
  $map = [
    2556 => ['haus-imperial-studio', 'imperial-studio-availability', 'haus-imperial-studio-book', 'haus-imperial-studio-review'],
    2559 => ['de/haus-imperial-studio-de', 'de/haus-imperial-studio-verfugbarkeit', 'de/haus-imperial-studio-buchen', 'de/haus-imperial-studio-review-de'],
    2562 => ['fr/haus-imperial-studio-fr', 'fr/haus-imperial-studio-disponibilite', 'fr/haus-imperial-studio-reservation', 'fr/haus-imperial-studio-review-fr'],
  ];
  $out = [];
  foreach ($map as $to_id => $srcs) {
    $to = get_permalink($to_id);
    if (!$to || get_post_status($to_id) !== 'publish') throw new Exception("target $to_id not published");
    if ($wpdb->get_var($wpdb->prepare("SELECT id FROM {$P}rank_math_redirections WHERE url_to=%s", $to))) { $out[$to_id] = 'exists'; continue; }
    $sources = array_map(function ($s) { return ['pattern' => $s, 'comparison' => 'exact', 'ignore' => '']; }, $srcs);
    $data = ['sources' => $sources, 'url_to' => $to, 'header_code' => 301, 'status' => 'active'];
    $r = \RankMath\Redirections\Redirection::from($data);
    $id = $r->save();
    $out[$to_id] = ['id' => $id, 'to' => $to];
  }
  $out['rows'] = $wpdb->get_results("SELECT id,sources,url_to,header_code,status FROM {$P}rank_math_redirections");
  return $out;
});

// 2. Menu items (delete; no trash for menu items)
$step('menus', function () use (&$backup) {
  $out = [];
  foreach ([2286, 1461, 1458, 2288, 1637, 1636, 2291, 1646, 1642, 1618, 1619] as $id) {
    $p = get_post($id);
    if (!$p) { $out[$id] = 'missing'; continue; }
    if ($p->post_type !== 'nav_menu_item') { $out[$id] = 'SKIP not menu item'; continue; }
    $backup['menu'][$id] = ['post' => $p, 'meta' => get_post_meta($id), 'menus' => wp_get_object_terms($id, 'nav_menu', ['fields' => 'ids'])];
    $out[$id] = wp_delete_post($id, true) ? 'deleted' : 'FAILED';
  }
  return $out;
});

// 3. Trash the Studio pages + unused Elementor template 87
$step('trash', function () use ($wpdb, $P) {
  $n = (int)$wpdb->get_var("SELECT COUNT(*) FROM {$P}rank_math_redirections WHERE status='active' AND (url_to LIKE '%no-longer-available%' OR url_to LIKE '%nicht-mehr-verfuegbar%' OR url_to LIKE '%plus-disponible%')");
  if ($n !== 3) throw new Exception("only $n redirects present - not trashing pages");
  $out = [];
  foreach ([143, 323, 328, 411, 678, 706, 2036, 2037, 2038, 894, 913, 918, 87] as $id) {
    $p = get_post($id);
    if (!$p) { $out[$id] = 'missing'; continue; }
    if ($id !== 87 && stripos($p->post_title, 'Studio') === false) { $out[$id] = 'SKIP title ' . $p->post_title; continue; }
    if ($p->post_status === 'trash') { $out[$id] = 'already'; continue; }
    $out[$id] = wp_trash_post($id) ? 'trashed' : 'FAILED';
  }
  return $out;
});

// 4+5. Elementor: headers, Home and Apartments pages
$step('elementor', function () use ($EL_PLAN, $el_transform) {
  $out = [];
  foreach ($EL_PLAN as $id => $plan) {
    try {
      $els = json_decode(get_post_meta($id, '_elementor_data', true), true);
      if (!is_array($els)) throw new Exception('no elementor data');
      [$new, $notes] = $el_transform($els, $plan);
      $doc = \Elementor\Plugin::$instance->documents->get($id, false);
      $doc->save(['elements' => $new]);
      $check = get_post_meta($id, '_elementor_data', true);
      $out[$id] = implode(';', $notes) . (stripos($check, 'Studio') === false || in_array($id, [], true) ? ' | no-studio' : ' | STILL HAS studio');
    } catch (Throwable $e) { $out[$id] = 'ERROR: ' . $e->getMessage(); }
  }
  return $out;
});

// 6. WPForms contact form: remove the Studio choice
$step('wpforms', function () use (&$backup) {
  $p = get_post(197);
  $f = json_decode($p->post_content, true);
  $backup['wpforms_197'] = $p->post_content;
  if (($f['fields'][5]['choices'][3]['label'] ?? '') !== 'Haus Imperial Studio') return 'choice not found / already gone';
  unset($f['fields'][5]['choices'][3]);
  $r = wp_update_post(['ID' => 197, 'post_content' => wp_slash(wp_json_encode($f))], true);
  if (is_wp_error($r)) throw new Exception($r->get_error_message());
  $chk = json_decode(get_post(197)->post_content, true);
  return array_column($chk['fields'][5]['choices'], 'label');
});

// 7. Review slider
$step('reviews', function () use ($wpdb, $P, &$backup) {
  $studio = ['894', '913', '918', 'ImperialStudio-AirBnB_rfAirbnb', 'ImperialStudio-VRBO_rfVRBO'];
  $out = [];
  $rp = json_decode($wpdb->get_var("SELECT rpage FROM {$P}wpfb_post_templates WHERE id=4"), true);
  $backup['tpl4_rpage'] = $rp;
  $keep = array_values(array_diff($rp, $studio));
  $wpdb->update("{$P}wpfb_post_templates", ['rpage' => wp_json_encode($keep)], ['id' => 4]);
  $out['tpl4'] = $keep;
  // overall "All Apartments" badges: limit to the remaining sources
  foreach ([4, 5, 6] as $bid) {
    $backup['badge_rpage'][$bid] = $wpdb->get_var("SELECT rpage FROM {$P}wpfb_badges WHERE id=$bid");
    $wpdb->update("{$P}wpfb_badges", ['rpage' => wp_json_encode($keep)], ['id' => $bid]);
  }
  $out['badges456'] = $wpdb->get_results("SELECT id,rpage FROM {$P}wpfb_badges WHERE id IN (4,5,6)");
  // Studio-only items
  $del = ['wpfb_post_templates' => [6], 'wpfb_badges' => [14, 15, 16], 'wpfb_forms' => [3, 10, 12], 'wpfb_reviewfunnel' => [6, 7]];
  foreach ($del as $t => $ids) foreach ($ids as $id) {
    $row = $wpdb->get_row("SELECT * FROM {$P}$t WHERE id=" . (int)$id, ARRAY_A);
    if (!$row) { $out["$t/$id"] = 'missing'; continue; }
    if (stripos($row['title'], 'Studio') === false) { $out["$t/$id"] = 'SKIP ' . $row['title']; continue; }
    $backup['rows'][$t][$id] = $row;
    $out["$t/$id"] = $wpdb->delete("{$P}$t", ['id' => $id]) ? 'deleted' : 'FAILED';
  }
  return $out;
});

// 8. WP Booking System: disable Studio emails, trash form 5 + calendar 5 (bookings kept)
$step('wpbs', function () use ($wpdb, $P) {
  $out = [];
  foreach (range(35, 45) as $eid) {
    $form = $wpdb->get_var("SELECT form_id FROM {$P}wpbs_emails WHERE id=$eid");
    if ((int)$form !== 5) { $out["email$eid"] = 'SKIP form ' . $form; continue; }
    wpbs_update_email($eid, ['is_enabled' => 0, 'date_modified' => current_time('Y-m-d H:i:s')]);
  }
  wpbs_update_form(5, ['status' => 'trash', 'date_modified' => current_time('Y-m-d H:i:s')]);
  wpbs_update_calendar(5, ['status' => 'trash', 'date_modified' => current_time('Y-m-d H:i:s')]);
  $out['emails'] = $wpdb->get_results("SELECT id,is_enabled FROM {$P}wpbs_emails WHERE form_id=5");
  $out['form5'] = $wpdb->get_var("SELECT status FROM {$P}wpbs_forms WHERE id=5");
  $out['cal5'] = $wpdb->get_var("SELECT status FROM {$P}wpbs_calendars WHERE id=5");
  $out['cal5_bookings_kept'] = $wpdb->get_var("SELECT COUNT(*) FROM {$P}wpbs_bookings WHERE calendar_id=5");
  return $out;
});

// 9. Elementor CSS cache
$step('elementor_cache', function () { \Elementor\Plugin::$instance->files_manager->clear_cache(); return 'cleared'; });

file_put_contents("$B/zz-step-b-backup-r8w2.json", wp_json_encode($backup, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
file_put_contents("$B/zz-step-b-r8w2.json", wp_json_encode($log, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
