<?php

$kirby->response()->header('X-Custom', 'yes')->code(201);
echo json_encode(['id' => $page->id()]);
