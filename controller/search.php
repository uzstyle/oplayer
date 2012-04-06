<?php
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Pagerfanta\Pagerfanta;
use Pagerfanta\View\DefaultView;

$app->get('/search/{q}', function(Request $request, $q) use($app) {
    $seo = Reg::get('seo');
    $seo['title'] = "Слушать {$q} онлайн.";
    Reg::set('seo', $seo);

    $p = $request->get('p', 1);
    $ipp = $app['conf']->getOption('app', 'itemsPerPage', 10) ;
    $pagerfanta = new Pagerfanta(new Art\OpenPlayerPagerfantaAdapter( 
        $app['openplayer'], $q, $p, $ipp
    ));
    $pagerfanta->setCurrentPage($p);
    $pagerfanta->setMaxPerPage($ipp);

    $view = new DefaultView();
    $pagination = $view->render($pagerfanta, function($page) use ($q) { 
            return "./search/{$q}?p={$page}";
        }, array(
            'proximity' => 5,
            'next_message' => 'Вперед',
            'previous_message' => 'Назад',
        )
    );

    $similar = array();
    if ( !strpos($q, " ") ) {
        $lastfmdata = Art\LastFM::request($app['conf'], "artist.getSimilar", array(
            "limit" => 10,
            "artist" => $q
        ));

        if ( isset($lastfmdata->similarartists) ) {
            $similar = $lastfmdata->similarartists->artist;
        }
    }

    return $app['view']->render('layout.phtml', 'search/list.phtml', array(
        'res' => $pagerfanta,
        'pagination' => $pagination,
        'q' => $q,
        'similar' => $similar
    ));
});

$app->get('/track/{vkid}', function(Request $request, $vkid) use($app) {
    $vtrack = $app['openplayer']->audioGetById($vkid)->audio;

    $seo = Reg::get('seo');
    $seo['title'] = "Слушать {$vtrack->artist} - {$vtrack->title} онлайн.";
    Reg::set('seo', $seo);

    $track = (array)$vtrack;
    $track['vkid'] = "{$vtrack->owner_id}_{$vtrack->aid}";

    return $app['view']->render('layout.phtml', 'part/track.phtml', array(
        'track' => $track
    ));
});

$app->get('/getlyrics', function(Request $request) use($app) { 
    $text = nl2br($app['openplayer']->audioGetLyrics( 
        $request->get('lyricsId')
    ));

    return new Response($text);
});

$app->get('/getsong/{vkid}', function(Request $request, $vkid) use($app) {
    session_write_close();

    $vkTrack = $app['openplayer']->getTrack( $vkid );
    header("Content-Length: {$vkTrack['size']}");

    if ( $request->get('dl') ) {
        header('Last-Modified:');
        header('ETag:');
        header('Content-Type: audio/mpeg');
        header('Accept-Ranges: bytes');

        header("Content-Disposition: attachment; filename=\"{$vkTrack['fname']}\"");
        header('Content-Description: File Transfer');
        header('Content-Transfer-Encoding: binary');
    }

    return $app->stream(function () use ($vkTrack) {
        readfile($vkTrack['url']);
    }, 200, array('Content-Type' => 'audio/mpeg'));
});