<?php
declare(strict_types=1);

namespace App\Services;

interface PlatformAdapter
{
    public function slug(): string;

    /**
     * @param array{page?:int,page_size?:int,locale?:?string,keyword?:?string,genre?:?int} $filters
     * @return array{platform_id:?int,page:int,page_size:int,total:?int,items:array<int,array<string,mixed>>}
     */
    public function listSeries(array $filters): array;

    /** @return array<int,array{id:int|string,name:string}> */
    public function genres(?string $locale = null): array;

    /** @return array{title:string,description:?string,cover:?string,episode_count:?int,genre:?string,extras:array<string,mixed>} */
    public function detail(string $seriesId): array;

    /**
     * @return array{
     *   series_id:string,
     *   episodes:array<int,array{
     *     episode:int,
     *     id?:string,
     *     locked:bool,
     *     duration?:?int,
     *     cover?:?string,
     *     sources:array<int,array{quality:string,codec:string,url:string,kid?:?string,mime?:string}>,
     *     subtitles:array<int,array{lang:string,label:string,vtt?:string,srt?:string}>
     *   }>
     * }
     */
    public function episodes(string $seriesId): array;
}
