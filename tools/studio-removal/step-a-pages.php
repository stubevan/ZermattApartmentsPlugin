// Step A: create the three "apartment no longer available" pages (EN/DE/FR).
wp_set_current_user(4);
$up = wp_upload_dir(); @unlink($up['basedir'] . '/zz-probe-7f3k2.json');
$log = [];
$cfg = [
  'en' => ['contact' => 165, 'apts' => 148, 'title' => 'Apartment no longer available', 'slug' => 'apartment-no-longer-available', 'hero' => 'No Longer Available',
    'text' => 'Sorry, this apartment is no longer available. Please contact Nicki to discuss further.', 'btn' => 'Contact Nicki'],
  'de' => ['contact' => 246, 'apts' => 1186, 'title' => 'Wohnung nicht mehr verfügbar', 'slug' => 'wohnung-nicht-mehr-verfuegbar', 'hero' => 'Nicht mehr verfügbar',
    'text' => 'Leider ist diese Wohnung nicht mehr verfügbar. Bitte kontaktieren Sie Nicki, um weitere Möglichkeiten zu besprechen.', 'btn' => 'Nicki kontaktieren'],
  'fr' => ['contact' => 251, 'apts' => 1177, 'title' => 'Appartement plus disponible', 'slug' => 'appartement-plus-disponible', 'hero' => 'Plus disponible',
    'text' => "Désolé, cet appartement n'est plus disponible. Veuillez contacter Nicki pour en discuter.", 'btn' => 'Contacter Nicki'],
];
$newid = function () { return substr(md5(uniqid('', true) . mt_rand()), 0, 7); };
$reid = function ($els) use (&$reid, $newid) { foreach ($els as &$e) { $e['id'] = $newid(); if (!empty($e['elements'])) $e['elements'] = $reid($e['elements']); } return $els; };
$find = function ($els, $id) use (&$find) { foreach ($els as $e) { if ($e['id'] === $id) return $e; if (!empty($e['elements']) && ($f = $find($e['elements'], $id))) return $f; } return null; };
$data = function ($pid) { return json_decode(get_post_meta($pid, '_elementor_data', true), true); };
$tr = [];
try {
foreach ($cfg as $lang => $c) {
  $existing = get_page_by_path($c['slug'], OBJECT, 'page');
  if ($existing && $existing->post_status !== 'trash') { $tr[$lang] = $existing->ID; $log[$lang] = 'exists ' . $existing->ID; continue; }
  // Hero: first section of that language's Contact page, with a new title.
  $hero = $data($c['contact'])[0];
  $done = false;
  foreach ($hero['elements'][0]['elements'] as &$w) if (($w['widgetType'] ?? '') === 'heading') { $w['settings']['title'] = $c['hero']; $done = true; break; }
  unset($w);
  if (!$done) throw new Exception("$lang: hero heading not found");
  // Body: the "want more information?" section of that language's Apartments page.
  $cta = $find($data($c['apts']), '31fdaa27');
  if (!$cta) throw new Exception("$lang: CTA section 31fdaa27 not found");
  $col = &$cta['elements'][0]['elements'];
  $col = array_values(array_filter($col, function ($w) { return ($w['widgetType'] ?? '') !== 'heading'; }));
  $seen = [];
  foreach ($col as &$w) {
    if ($w['widgetType'] === 'text-editor') { $w['settings']['editor'] = '<p>' . esc_html($c['text']) . '</p>'; $seen[] = 'text'; }
    if ($w['widgetType'] === 'button') { $w['settings']['text'] = $c['btn']; $w['settings']['link']['url'] = get_permalink($c['contact']); $seen[] = 'button'; }
  }
  unset($w, $col);
  if ($seen !== ['text', 'button']) throw new Exception("$lang: CTA widgets unexpected: " . implode(',', $seen));
  $elements = $reid([$hero, $cta]);
  $pid = wp_insert_post(['post_type' => 'page', 'post_status' => 'draft', 'post_title' => $c['title'], 'post_name' => $c['slug'], 'comment_status' => 'closed', 'ping_status' => 'closed'], true);
  if (is_wp_error($pid)) throw new Exception("$lang: insert failed " . $pid->get_error_message());
  pll_set_post_language($pid, $lang);
  update_post_meta($pid, '_wp_page_template', 'elementor_header_footer');
  update_post_meta($pid, '_elementor_edit_mode', 'builder');
  update_post_meta($pid, '_elementor_template_type', 'wp-page');
  update_post_meta($pid, 'rank_math_robots', ['noindex', 'follow']);
  $doc = \Elementor\Plugin::$instance->documents->get($pid, false);
  $doc->save(['elements' => $elements]);
  wp_update_post(['ID' => $pid, 'post_status' => 'publish', 'post_name' => $c['slug']]);
  $tr[$lang] = $pid;
  $log[$lang] = ['id' => $pid, 'url' => get_permalink($pid), 'lang' => pll_get_post_language($pid), 'tpl' => get_post_meta($pid, '_wp_page_template', true)];
}
pll_save_post_translations($tr);
$log['translations'] = pll_get_post_translations($tr['en']);
} catch (Throwable $e) { $log['ERROR'] = $e->getMessage() . ' @' . $e->getLine(); }
file_put_contents($up['basedir'] . '/zz-step-a-9q4m.json', wp_json_encode($log, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
