<?php

use Spatie\Fork\Connection;

test('it can create a connected pair of sockets', function () {
    [$socketToParent, $socketToChild] = Connection::createPair();

    $socketToChild->write('hello');

    $received = implode('', iterator_to_array($socketToParent->read(), false));

    $socketToParent->close();
    $socketToChild->close();

    expect($received)->toBe('hello');
});
