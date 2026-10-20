<?php

use sale\booking\followup\Task as BookingTask;
use sale\camp\followup\Task as CampTask;

/**
 * Bookings
 */

$tasks = BookingTask::search([
    ['object_class', '=', null],
    ['entity', '<>', null]
])
    ->read(['entity'])
    ->get();

$map_entities_ids = [];
foreach($tasks as $id => $task) {
    $map_entities_ids[$task['entity']][] = $id;
}

foreach($map_entities_ids as $entity => $ids) {
    BookingTask::ids($ids)->update(['object_class' => $entity]);
}

$tasks = BookingTask::search([
    ['object_id', '=', null],
    ['entity_id', '<>', null]
])
    ->read(['entity_id'])
    ->get();

foreach($tasks as $id => $task) {
    BookingTask::id($id)->update(['object_id' => $task['entity_id']]);
}


/**
 * Camps
 */

$tasks = CampTask::search([
    ['object_class', '=', null],
    ['entity', '<>', null]
])
    ->read(['entity'])
    ->get();

$map_entities_ids = [];
foreach($tasks as $id => $task) {
    $map_entities_ids[$task['entity']][] = $id;
}

foreach($map_entities_ids as $entity => $ids) {
    CampTask::ids($ids)->update(['object_class' => $entity]);
}

$tasks = CampTask::search([
    ['object_id', '=', null],
    ['entity_id', '<>', null]
])
    ->read(['entity_id'])
    ->get();

foreach($tasks as $id => $task) {
    CampTask::id($id)->update(['object_id' => $task['entity_id']]);
}
