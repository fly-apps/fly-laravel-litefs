<?php

namespace App\Http\Middleware;

use Closure;
use Log;
use Illuminate\Http\Request;

class FlyReplayLiteFSWrite
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure(\Illuminate\Http\Request): (\Illuminate\Http\Response|\Illuminate\Http\RedirectResponse)  $next
     * @return \Illuminate\Http\Response|\Illuminate\Http\RedirectResponse
     */
    public function handle(Request $request, Closure $next)
    {
        Log::info('Request from '.env('FLY_REGION').' AND '.env('FLY_ALLOC_ID') );
        // Get the Primary Node Instance Id
        $primaryNodeId = $this->readPrimaryNodeId();

        // Replicas will always have a primary node id reference
        // If we can get the primary node id, that means 
        // Our current instance is a replica, time to forward!
        if( $primaryNodeId !== '' ){
            return response('', 200, [
                'fly-replay' => "instance=$primaryNodeId",
            ]);
        }

        // Not Having a primary node id means 
        // our current instance is the primary, 
        // process the request now!
        return $next($request);
    }

    function readPrimaryNodeId()
    {
        $primaryNodeId = '';
        $pathToPrimaryId = '/var/www/html/storage/database/.primary';
        if( file_exists( $pathToPrimaryId ) ) {
            $primaryNodeId = file_get_contents( $pathToPrimaryId );
        }
        return $primaryNodeId;
    }


}
