<?php

use Vulpo\Seo\Redirects\Redirect;
use Vulpo\Seo\Redirects\RedirectRepository;

beforeEach(function () {
    $this->repository = app(RedirectRepository::class);
    $this->repository->flush();
});

it('stores and reads redirects', function () {
    $this->repository->add(new Redirect(from: '/old', to: '/new'));

    expect($this->repository->has('/old'))->toBeTrue();
    expect($this->repository->all())->toHaveCount(1);
    expect($this->repository->resolve('/old'))->toBe(['to' => '/new', 'status' => 301, 'consumed_query' => false]);
});

it('replaces an existing rule for the same source', function () {
    $this->repository->add(new Redirect(from: '/old', to: '/first'));
    $this->repository->add(new Redirect(from: '/old', to: '/second'));

    expect($this->repository->all())->toHaveCount(1);
    expect($this->repository->resolve('/old')['to'])->toBe('/second');
});

it('follows a chain to its final destination', function () {
    $this->repository->save([
        ['from' => '/a', 'to' => '/b'],
        ['from' => '/b', 'to' => '/c'],
    ]);

    expect($this->repository->resolve('/a')['to'])->toBe('/c');
});

it('does not loop forever on a circular chain', function () {
    $this->repository->save([
        ['from' => '/a', 'to' => '/b'],
        ['from' => '/b', 'to' => '/a'],
    ]);

    expect($this->repository->resolve('/a'))->not->toBeNull();
});

it('keeps the redirect status', function () {
    $this->repository->add(new Redirect(from: '/old', to: '/new', status: 302));

    expect($this->repository->resolve('/old')['status'])->toBe(302);
});

it('removes redirects', function () {
    $this->repository->add(new Redirect(from: '/old', to: '/new'));
    $this->repository->remove('/old');

    expect($this->repository->all())->toBeEmpty();
    expect($this->repository->resolve('/old'))->toBeNull();
});

it('matches a legacy query string URL', function () {
    $this->repository->save([
        ['from' => '/index.php?id=42', 'to' => '/about'],
        ['from' => '/index.php', 'to' => '/home'],
    ]);
    $this->repository->flush();

    // The query string rule wins for the full URL...
    expect($this->repository->resolve('/index.php', null, 'id=42')['to'])->toBe('/about');
    // ...and the plain rule still handles the bare path.
    expect($this->repository->resolve('/index.php')['to'])->toBe('/home');
});

it('ignores a query string rule when the request has no query', function () {
    $this->repository->save([['from' => '/index.php?id=42', 'to' => '/about']]);
    $this->repository->flush();

    expect($this->repository->resolve('/index.php'))->toBeNull();
});

it('leaves a plain rule matching regardless of the query string', function () {
    $this->repository->save([['from' => '/old', 'to' => '/new']]);
    $this->repository->flush();

    expect($this->repository->resolve('/old', null, 'utm_source=newsletter')['to'])->toBe('/new');
});
