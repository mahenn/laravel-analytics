<?php

namespace Spatie\Analytics;

use Carbon\Carbon;
use Google_Service_Analytics;
use Illuminate\Support\Collection;
use Illuminate\Support\Traits\Macroable;

class Analytics
{
    use Macroable;

    /** @var \Spatie\Analytics\AnalyticsClient */
    protected $client;

    /** @var string */
    protected $viewId;

    /**
     * @param \Spatie\Analytics\AnalyticsClient $client
     * @param string                            $viewId
     */
    public function __construct(AnalyticsClient $client, $viewId)
    {
        $this->client = $client;

        $this->viewId = $viewId;
    }

    /**
     * @param string $viewId
     *
     * @return $this
     */
    public function setViewId($viewId)
    {
        $this->viewId = $viewId;

        return $this;
    }

    public function fetchVisitorsAndPageViews(Period $period,$page = null)
    {
        $response = $this->performQuery(
            $period,
            'ga:users,ga:pageviews',
            ['dimensions' => 'ga:date,ga:pageTitle','filters' => "ga:pagePath=~/".$page]
        );

        return collect(isset($response['rows'])?$response['rows']:[])->map(function (array $dateRow) {
            return [
                'date' => Carbon::createFromFormat('Ymd', $dateRow[0]),
                'pageTitle' => $dateRow[1],
                'visitors' => (int) $dateRow[2],
                'pageViews' => (int) $dateRow[3],
            ];
        });
    }

    public function fetchTotalVisitorsAndPageViews(Period $period,$page = null)
    {
        $response = $this->performQuery(
            $period,
            'ga:visits',
            ['dimensions' => 'ga:date','filters' =>"ga:pagePath=~/".$page]           
        );
        
        return collect(isset($response['rows'])?$response['rows']:[])->map(function (array $dateRow) {
            return [
                'date' => Carbon::createFromFormat('Ymd', $dateRow[0]),
                'visits' => (int) $dateRow[1]
                
            ];
        });
    }

    public function fetchMostVisitedPages(Period $period, $maxResults = 20)
    {
        $response = $this->performQuery(
            $period,
            'ga:pageviews',
            [
                'dimensions' => 'ga:pagePath,ga:pageTitle',
                'sort' => '-ga:pageviews',
                'max-results' => $maxResults,
            ]
        );

        return collect(isset($response['rows'])?$response['rows']:[])
            ->map(function (array $pageRow) {
                return [
                    'url' => $pageRow[0],
                    'pageTitle' => $pageRow[1],
                    'pageViews' => (int) $pageRow[2],
                ];
            });
    }

    public function fetchTopSource(Period $period, $maxResults = 20,$page=null)
    {
        $response = $this->performQuery($period,
            'ga:pageviews',
            [
                //'dimensions' => 'ga:fullReferrer',
                'dimensions' => 'ga:source,ga:medium',
                'sort' => '-ga:pageviews',
                'max-results' => $maxResults,
                'filters' =>"ga:pagePath=~/".$page,
            ]
        );
        //dd($response['rows']);
        return collect(isset($response['rows'])?$response['rows']:[])->map(function (array $pageRow) {
            return [
                'source' => $pageRow[0],
                'medium' => $pageRow[1],
                'pageViews' => (int) $pageRow[2],
            ];
        });
    }

    public function fetchTopCountries(Period $period, $maxResults = 20,$page=null)
    {
        $response = $this->performQuery($period,
            'ga:sessions,ga:pageviews',
            [
                'dimensions' => 'ga:country',
                'sort' => '-ga:sessions,-ga:pageviews',
                'max-results' => $maxResults,
                'filters' =>"ga:pagePath=~/".$page,
            ]
        );
        
        return collect(isset($response['rows'])?$response['rows']:[])->map(function (array $pageRow) {
            return [
                'country' => $pageRow[0],
                'sessions' => (int) $pageRow[1],
                'pageViews' => (int) $pageRow[2],
            ];
        });
    }

    public function fetchTopDevices(Period $period,$maxResults = 20, $page = null)
    {
        $response = $this->performQuery(
            $period,
            'ga:sessions',
            ['dimensions' => 'ga:deviceCategory',
            'sort' => '-ga:sessions',
            'max-results' => $maxResults,
            'filters' =>"ga:pagePath=~/".$page]           
        );

        return collect(isset($response['rows'])?$response['rows']:[])->map(function (array $dateRow) {
            return [
                'device' => $dateRow[0],
                'sessions' => (int) $dateRow[1],
            ];
        });
    }

    public function fetchTopBrowsers(Period $period, $maxResults = 10,$page=null)
    {
        $response = $this->performQuery(
            $period,
            'ga:sessions',
            [
                'dimensions' => 'ga:browser',
                'sort' => '-ga:sessions',
                'filters' =>"ga:pagePath=~/".$page
            ]
        );

        $topBrowsers = collect(isset($response['rows'])?$response['rows']:[])->map(function (array $browserRow) {
            return [
                'browser' => $browserRow[0],
                'sessions' => (int) $browserRow[1],
            ];
        });

        if ($topBrowsers->count() <= $maxResults) {
            return $topBrowsers;
        }

        return $this->summarizeTopBrowsers($topBrowsers, $maxResults);
    }

    protected function summarizeTopBrowsers(Collection $topBrowsers, $maxResults)
    {
        return $topBrowsers
            ->take($maxResults - 1)
            ->push([
                'browser' => 'Others',
                'sessions' => $topBrowsers->splice($maxResults - 1)->sum('sessions'),
            ]);
    }

    /**
     * Call the query method on the authenticated client.
     *
     * @param Period $period
     * @param string $metrics
     * @param array  $others
     *
     * @return array|null
     */
    public function performQuery(Period $period, $metrics, array $others = [])
    {
        return $this->client->performQuery(
            $this->viewId,
            $period->startDate,
            $period->endDate,
            $metrics,
            $others
        );
    }

    /**
     * Get the underlying Google_Service_Analytics object. You can use this
     * to basically call anything on the Google Analytics API.
     *
     * @return \Google_Service_Analytics
     */
    public function getAnalyticsService()
    {
        return $this->client->getAnalyticsService();
    }
}
