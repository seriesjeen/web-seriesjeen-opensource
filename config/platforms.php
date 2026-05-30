<?php
use App\Services\Adapters\BilitvAdapter;
use App\Services\Adapters\CubetvAdapter;
use App\Services\Adapters\DotdramaAdapter;
use App\Services\Adapters\DramabiteAdapter;
use App\Services\Adapters\DramaboxAdapter;
use App\Services\Adapters\DramanovaAdapter;
use App\Services\Adapters\DramawaveAdapter;
use App\Services\Adapters\FlextvAdapter;
use App\Services\Adapters\FlickreelsAdapter;
use App\Services\Adapters\FreereelsAdapter;
use App\Services\Adapters\FundramaAdapter;
use App\Services\Adapters\GenericAdapter;
use App\Services\Adapters\GoodshortAdapter;
use App\Services\Adapters\HappyshortAdapter;
use App\Services\Adapters\IdramaAdapter;
use App\Services\Adapters\MeloloAdapter;
use App\Services\Adapters\MicrodramaAdapter;
use App\Services\Adapters\NetshortAdapter;
use App\Services\Adapters\RapidtvAdapter;
use App\Services\Adapters\ReelalaAdapter;
use App\Services\Adapters\ReelifeAdapter;
use App\Services\Adapters\ReelshortAdapter;
use App\Services\Adapters\ShortmaxAdapter;
use App\Services\Adapters\ShortwaveAdapter;
use App\Services\Adapters\StardusttvAdapter;
use App\Services\Adapters\VeloloAdapter;
use App\Services\Adapters\ViglooAdapter;

return [
    'bilitv'     => ['adapter' => BilitvAdapter::class,     'display' => 'BiliTV'],
    'cubetv'     => ['adapter' => CubetvAdapter::class,     'display' => 'CubeTV'],
    'dotdrama'   => ['adapter' => DotdramaAdapter::class,   'display' => 'DotDrama'],
    'dramabite'  => ['adapter' => DramabiteAdapter::class,  'display' => 'DramaBite'],
    'dramabox'   => ['adapter' => DramaboxAdapter::class,   'display' => 'DramaBox'],
    'dramanova'  => ['adapter' => DramanovaAdapter::class,  'display' => 'DramaNova'],
    'dramawave'  => ['adapter' => DramawaveAdapter::class,  'display' => 'DramaWave'],
    'flextv'     => ['adapter' => FlextvAdapter::class,     'display' => 'FlexTV'],
    'flickreels' => ['adapter' => FlickreelsAdapter::class, 'display' => 'FlickReels'],
    'freereels'  => ['adapter' => FreereelsAdapter::class,  'display' => 'FreeReels'],
    'fundrama'   => ['adapter' => FundramaAdapter::class,   'display' => 'FunDrama'],
    'goodshort'  => ['adapter' => GoodshortAdapter::class,  'display' => 'GoodShort'],
    'happyshort' => ['adapter' => HappyshortAdapter::class, 'display' => 'HappyShort'],
    'idrama'     => ['adapter' => IdramaAdapter::class,     'display' => 'iDrama'],
    'melolo'     => ['adapter' => MeloloAdapter::class,     'display' => 'Melolo'],
    'microdrama' => ['adapter' => MicrodramaAdapter::class, 'display' => 'MicroDrama'],
    'netshort'   => ['adapter' => NetshortAdapter::class,   'display' => 'NetShort'],
    'rapidtv'    => ['adapter' => RapidtvAdapter::class,    'display' => 'RapidTV'],
    'reelala'    => ['adapter' => ReelalaAdapter::class,    'display' => 'ReelAla'],
    'reelife'    => ['adapter' => ReelifeAdapter::class,    'display' => 'ReelLife'],
    'reelshort'  => ['adapter' => ReelshortAdapter::class,  'display' => 'ReelShort'],
    'shortmax'   => ['adapter' => ShortmaxAdapter::class,   'display' => 'ShortMax'],
    'shortwave'  => ['adapter' => ShortwaveAdapter::class,  'display' => 'ShortWave'],
    'stardusttv' => ['adapter' => StardusttvAdapter::class, 'display' => 'StardustTV'],
    'velolo'     => ['adapter' => VeloloAdapter::class,     'display' => 'Velolo'],
    'vigloo'     => ['adapter' => ViglooAdapter::class,     'display' => 'Vigloo'],
];
