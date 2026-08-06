<?= json_encode(['id' => $page->id(), 'lang' => kirby()->language()->code(), 'translation' => \Kirby\Toolkit\I18n::locale()]);
