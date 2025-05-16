@extends('layouts.app')

@section('title', 'Settings')

@section('content')
<div class="card">
<div class="card-body">

<div class="row row-cols-1 row-cols-md-2 row-cols-xl-4">
  <div class="col">
    <div class="card rounded-1" style="color: #FFFFFF; background: #194F90; min-height:120px;">
      <a href="http://localhost/staff/staff">
        <div class="card-body">
          <div class="d-flex align-items-center justify-content-between">
            <div>
              <h5>
                <p class="mb-0 text-white">Main Staff <b style="font-size:9px; color:black;">&lt; (Active &amp; Due)</b></p>
              </h5>
              <h5 style="color:#FFFFFF;">
                398               </h5>
            </div>
            <div class="fs-1 text-white"><i class="bx bxs-wallet"></i></div>
          </div>
        </div>
      </a>
    </div>
  </div>

  <div class="col">
    <div class="card rounded-1" style="color: #000000; background: #DAE343; min-height:120px;">
      <a href="http://localhost/staff/staff/contract_status/2">
        <div class="card-body">
          <div class="d-flex align-items-center justify-content-between">
            <div>
              <h5>
                <p class="mb-0 text-white">Contracts Due <b style="font-size:9px; color:black;">&lt; 3 Months</b></p>
              </h5>
              <h5 style="color:#FFFFFF;">
                59              </h5>
            </div>
            <div class="fs-1 text-white"><i class="bx bxs-wallet"></i></div>
          </div>
        </div>
      </a>
    </div>
  </div>
  <div class="col">
    <div class="card rounded-1" style="color: #000000; background: #DAE343; min-height:120px;">
      <a href="http://localhost/staff/staff/contract_status/7">
        <div class="card-body">
          <div class="d-flex align-items-center justify-content-between">
            <div>
              <h5>
                <p class="mb-0 text-white">Under Renewal</p>
              </h5>
              <h5 style="color:#FFFFFF;">
                0              </h5>
            </div>
            <div class="fs-1 text-white"><i class="bx bxs-bar-chart-alt-2"></i></div>
          </div>
        </div>
      </a>
    </div>
  </div>

  <div class="col">
    <div class="card rounded-1" style="color: #000000; background: #DAE343; min-height:120px;">
      <a href="http://localhost/staff/staff/contract_status/3">
        <div class="card-body">
          <div class="d-flex align-items-center justify-content-between">
            <div>
              <h5>
                <p class="mb-0 text-white">Expired Contracts</p>
              </h5>
              <h5 style="color:#FFFFFF;">
                84              </h5>
            </div>
            <div class="fs-1 text-white"><i class="bx bxs-wallet"></i></div>
          </div>
        </div>
      </a>
    </div>
  </div>


</div>

<!--end row-->

<div class="row">
  <div class="col-12 col-lg-6 d-flex">
    <div class="card rounded-1 w-100">
      <div class="card-body">
        <div class="d-flex align-items-center">
          <div>
            <h6 class="mb-0">Main Staff Gender Distribution</h6>
          </div>
        </div>
        <div>
          <figure class="highcharts-figure">
            <div id="container" data-highcharts-chart="0" aria-hidden="false" role="region" aria-label="Chart. Highcharts interactive chart." style="overflow: hidden;"><div id="highcharts-screen-reader-region-before-0" aria-hidden="false" style="position: relative;"><div aria-hidden="false" style="position: absolute; width: 1px; height: 1px; overflow: hidden; white-space: nowrap; clip: rect(1px, 1px, 1px, 1px); margin-top: -3px; opacity: 0.01;"><h6>Chart</h6><div>Pie chart with 2 slices.</div><div><button id="hc-linkto-highcharts-data-table-0" tabindex="-1" aria-expanded="false">View as data table, Chart</button></div></div></div><div aria-hidden="false" class="highcharts-announcer-container" style="position: relative;"><div aria-hidden="false" aria-live="polite" aria-atomic="true" style="position: absolute; width: 1px; height: 1px; overflow: hidden; white-space: nowrap; clip: rect(1px, 1px, 1px, 1px); margin-top: -3px; opacity: 0.01;"></div><div aria-hidden="false" aria-live="assertive" aria-atomic="true" style="position: absolute; width: 1px; height: 1px; overflow: hidden; white-space: nowrap; clip: rect(1px, 1px, 1px, 1px); margin-top: -3px; opacity: 0.01;"></div><div aria-hidden="false" aria-live="polite" aria-atomic="true" style="position: absolute; width: 1px; height: 1px; overflow: hidden; white-space: nowrap; clip: rect(1px, 1px, 1px, 1px); margin-top: -3px; opacity: 0.01;"></div><div aria-hidden="false" aria-live="polite" aria-atomic="true" style="position: absolute; width: 1px; height: 1px; overflow: hidden; white-space: nowrap; clip: rect(1px, 1px, 1px, 1px); margin-top: -3px; opacity: 0.01;"></div></div><div id="highcharts-6ffgtg1-0" dir="ltr" style="position: relative; overflow: hidden; width: 626px; height: 400px; text-align: left; line-height: normal; z-index: 0; -webkit-tap-highlight-color: rgba(0, 0, 0, 0); user-select: none; touch-action: manipulation; outline: none; padding: 0px;" class="highcharts-container " aria-hidden="false" tabindex="0"><div aria-hidden="false" class="highcharts-a11y-proxy-container-before" style="top: 0px; left: 0px; white-space: nowrap; position: absolute;"></div><svg version="1.1" class="highcharts-root" style="font-family: -apple-system, BlinkMacSystemFont, &quot;Segoe UI&quot;, Roboto, Helvetica, Arial, &quot;Apple Color Emoji&quot;, &quot;Segoe UI Emoji&quot;, &quot;Segoe UI Symbol&quot;, sans-serif; font-size: 1rem;" xmlns="http://www.w3.org/2000/svg" width="626" height="400" viewBox="0 0 626 400" aria-hidden="false" aria-label="Interactive chart"><desc aria-hidden="true">Created with Highcharts 12.2.0</desc><defs aria-hidden="true"><filter id="highcharts-drop-shadow-0"><feDropShadow dx="1" dy="1" flood-color="#000000" flood-opacity="0.75" stdDeviation="2.5"></feDropShadow></filter></defs><rect fill="#ffffff" class="highcharts-background" filter="none" x="0" y="0" width="626" height="400" rx="0" ry="0" aria-hidden="true"></rect><rect fill="none" class="highcharts-plot-background" x="10" y="10" width="606" height="375" filter="none" aria-hidden="true"></rect><g class="highcharts-pane-group" data-z-index="0" aria-hidden="true"></g><rect fill="none" class="highcharts-plot-border" data-z-index="1" stroke="#cccccc" stroke-width="0" x="10" y="10" width="606" height="375" aria-hidden="true"></rect><g class="highcharts-series-group" data-z-index="3" filter="none" aria-hidden="false"><g class="highcharts-series highcharts-series-0 highcharts-pie-series highcharts-tracker" data-z-index="0.1" opacity="1" transform="translate(10,10) scale(1 1)" filter="none" aria-hidden="false" clip-path="none" style="cursor: pointer;"><path fill="#28a745" d="M 399.5335130867144 269.63764880453505 A 0.5 0.5 0 0 1 399.58902081167815 270.3439711665321 A 127.25 127.25 0 1 1 302.4485302838088 60.25119497161428 A 0.5 0.5 0 0 1 302.9506971210952 60.75099578605703 L 302.9506971210952 60.75099578605703 A 0.5 0.5 0 0 1 302.4528640340343 61.251185580874676 A 126.25 126.25 0 1 0 398.82997153221504 269.69293799430005 A 0.5 0.5 0 0 1 399.5335130867144 269.63764880453505 Z" class="highcharts-halo highcharts-color-1" data-z-index="-1" fill-opacity="0.25" visibility="hidden"></path><path fill="#b4a269" d="M 302.9747009820106 63.286225160171185 A 3 3 0 0 1 306.04652468444925 60.28647403932614 A 127.25 127.25 0 0 1 401.87785789074894 267.598262895876 A 3 3 0 0 1 397.60264776817553 267.9947298601003 L 303 187.5 A 0 0 0 0 1 303 187.5 A 0 0 0 0 0 303 187.5 A 0 0 0 0 1 303 187.5 Z" transform="translate(0,0)" stroke="#ffffff" stroke-width="1" opacity="1" stroke-linejoin="round" class="highcharts-point highcharts-color-0" tabindex="-1" role="img" aria-label="Female, 146%. Percentage." style="outline: none;"></path><path fill="rgb(40,167,69)" d="M 397.60264776817553 267.9947298601003 A 3 3 0 0 1 397.8957747611679 272.27826627430926 A 127.25 127.25 0 1 1 299.8780823021614 60.28830191414029 A 3 3 0 0 1 302.95168327829526 63.28623198093375 L 303 187.5 A 0 0 0 0 1 303 187.5 A 0 0 0 1 0 303 187.5 A 0 0 0 0 1 303 187.5 Z" transform="translate(0,0)" stroke="#ffffff" stroke-width="1" opacity="1" stroke-linejoin="round" class="highcharts-point highcharts-color-1" tabindex="-1" role="img" aria-label="Male, 257%. Percentage." style="outline: none;"></path></g><g class="highcharts-markers highcharts-series-0 highcharts-pie-series" data-z-index="0.1" opacity="1" transform="translate(10,10) scale(1 1)" aria-hidden="true" clip-path="none"></g></g><g class="highcharts-exporting-group" data-z-index="3" aria-hidden="true"><g class="highcharts-no-tooltip highcharts-button highcharts-contextbutton" stroke-linecap="round" style="cursor: pointer;" transform="translate(588,5)"><title>Chart context menu</title><rect fill="#ffffff" class="highcharts-button-box" x="0.5" y="0.5" width="28" height="28" rx="2" ry="2" stroke="none" stroke-width="1"></rect><path fill="#666666" d="M 8 9.5 L 22 9.5 M 8 14.5 L 22 14.5 M 8 19.5 L 22 19.5" class="highcharts-button-symbol" data-z-index="1" stroke="#666666" stroke-width="3"></path><text x="28" data-z-index="1" y="18.5" text-anchor="end" style="font-size: 0.8em; font-weight: normal; fill: rgb(51, 51, 51);"></text></g></g><text x="313" class="highcharts-title" data-z-index="4" text-align="center" y="25" text-anchor="middle" transform-origin="10 22" transform="translate(0,0) scale(1 1)" style="font-size: 1.2em; font-weight: bold; fill: rgb(51, 51, 51);" aria-hidden="true"></text><text x="313" class="highcharts-subtitle" data-z-index="4" text-align="center" y="24" text-anchor="middle" transform-origin="10 15" transform="translate(0,0) scale(1 1)" style="font-size: 0.8em; fill: rgb(102, 102, 102);" aria-hidden="true"></text><text x="10" text-anchor="start" class="highcharts-caption" data-z-index="4" style="font-size: 0.8em; fill: rgb(102, 102, 102);" text-align="left" y="397" transform-origin="10 15" transform="translate(0,0) scale(1 1)" aria-hidden="true"></text><g class="highcharts-data-labels highcharts-series-0 highcharts-pie-series highcharts-tracker" data-z-index="6" opacity="1" transform="translate(10,10) scale(1 1)" aria-hidden="true" style="cursor: pointer;"><g class="highcharts-label highcharts-data-label highcharts-data-label-color-0" data-z-index="1" filter="none" style="cursor: pointer;" transform="translate(331,149)" opacity="1"><text x="5" data-z-index="1" y="19" style="font-size: 15px; font-weight: bold; fill: rgb(0, 0, 0);"><tspan class="highcharts-text-outline" fill="#FFFFFF" stroke="#FFFFFF" stroke-width="2px" stroke-linejoin="round" style="">146<tspan class="highcharts-br" dy="18" x="5">&ZeroWidthSpace;</tspan><tspan style="font-weight: bold;">Female</tspan><tspan class="highcharts-br" dy="18" x="5">&ZeroWidthSpace;</tspan>36.2 %<tspan x="5" dy="-36">&ZeroWidthSpace;</tspan></tspan>146<tspan class="highcharts-br" dy="18" x="5">&ZeroWidthSpace;</tspan><tspan style="font-weight: bold;">Female</tspan><tspan class="highcharts-br" dy="18" x="5">&ZeroWidthSpace;</tspan>36.2 %</text></g><g class="highcharts-label highcharts-data-label highcharts-data-label-color-1" data-z-index="1" filter="none" style="cursor: pointer;" transform="translate(209,205)" opacity="1"><text x="5" data-z-index="1" y="19" style="font-size: 15px; font-weight: bold; fill: rgb(0, 0, 0);"><tspan class="highcharts-text-outline" fill="#FFFFFF" stroke="#FFFFFF" stroke-width="2px" stroke-linejoin="round" style="">257<tspan class="highcharts-br" dy="18" x="5">&ZeroWidthSpace;</tspan><tspan style="font-weight: bold;">Male</tspan><tspan class="highcharts-br" dy="18" x="5">&ZeroWidthSpace;</tspan>63.8 %<tspan x="5" dy="-36">&ZeroWidthSpace;</tspan></tspan>257<tspan class="highcharts-br" dy="18" x="5">&ZeroWidthSpace;</tspan><tspan style="font-weight: bold;">Male</tspan><tspan class="highcharts-br" dy="18" x="5">&ZeroWidthSpace;</tspan>63.8 %</text></g></g><g class="highcharts-legend highcharts-no-tooltip" data-z-index="7" visibility="hidden" aria-hidden="true"><rect fill="none" class="highcharts-legend-box" rx="0" ry="0" stroke="#999999" stroke-width="0" filter="none" x="0" y="0" width="8" height="8"></rect><g data-z-index="1"><g></g></g></g><g class="highcharts-label highcharts-tooltip highcharts-color-1" data-z-index="8" filter="url(#highcharts-drop-shadow-0)" style="cursor: default; pointer-events: none;" transform="translate(235,145)" opacity="0" aria-hidden="true" visibility="hidden"><path fill="#ffffff" class="highcharts-label-box highcharts-tooltip-box" d="M 3 0 L 139 0 A 3 3 0 0 1 142 3 L 142 41 A 3 3 0 0 1 139 44 L 3 44 A 3 3 0 0 1 0 41 L 0 3 A 3 3 0 0 1 3 0 Z" stroke-width="0" stroke="#28a745"></path><text x="8" data-z-index="1" y="18" style="font-size: 0.8em; fill: rgb(51, 51, 51);"><tspan style="font-size: 0.8em;">Male</tspan><tspan class="highcharts-br" dy="15" x="8">&ZeroWidthSpace;</tspan>Percentage: <tspan style="font-weight: bold;">63.8%</tspan></text></g></svg><div aria-hidden="false" class="highcharts-a11y-proxy-container-after" style="top: 0px; left: 0px; white-space: nowrap; position: absolute;"><div class="highcharts-a11y-proxy-group highcharts-a11y-proxy-group-zoom"></div><div class="highcharts-a11y-proxy-group highcharts-a11y-proxy-group-chartMenu"><button class="highcharts-a11y-proxy-element highcharts-no-tooltip" aria-label="View chart menu, Chart" aria-expanded="false" title="Chart context menu" style="border-width: 0px; background-color: transparent; cursor: pointer; outline: none; opacity: 0.001; z-index: 999; overflow: hidden; padding: 0px; margin: 0px; display: block; position: absolute; width: 28px; height: 28px; left: 589px; top: 6px;"></button></div></div></div><div id="highcharts-screen-reader-region-after-0" aria-hidden="false" style="position: relative;"><div aria-hidden="false" style="position: absolute; width: 1px; height: 1px; overflow: hidden; white-space: nowrap; clip: rect(1px, 1px, 1px, 1px); margin-top: -3px; opacity: 0.01;"><div id="highcharts-end-of-chart-marker-0" class="highcharts-exit-anchor" tabindex="0" aria-hidden="false">End of interactive chart.</div></div></div></div>
          </figure>
        </div>
      </div>
    </div>
  </div>
  <div class="col-12 col-lg-6 d-flex">
    <div class="card rounded-1 w-100">
      <div class="card-body">
        <div class="d-flex align-items-center">
          <div>
            <h6 class="mb-0">Staff by Contract Type</h6>
          </div>
        </div>
        <div>
          <figure class="highcharts-figure">
            <div id="container3" data-highcharts-chart="1" aria-hidden="false" role="region" aria-label="Chart. Highcharts interactive chart." style="overflow: hidden;"><div id="highcharts-screen-reader-region-before-1" style="position: relative;" aria-hidden="false"><div aria-hidden="false" style="position: absolute; width: 1px; height: 1px; overflow: hidden; white-space: nowrap; clip: rect(1px, 1px, 1px, 1px); margin-top: -3px; opacity: 0.01;"><h6>Chart</h6><div>Bar chart with 6 bars.</div><div><button id="hc-linkto-highcharts-data-table-1" tabindex="-1" aria-expanded="false">View as data table, Chart</button></div><div>The chart has 1 X axis displaying categories. </div><div>The chart has 1 Y axis displaying Total Staff. Data ranges from 1 to 282.</div></div></div><div aria-hidden="false" class="highcharts-announcer-container" style="position: relative;"><div aria-hidden="false" aria-live="polite" aria-atomic="true" style="position: absolute; width: 1px; height: 1px; overflow: hidden; white-space: nowrap; clip: rect(1px, 1px, 1px, 1px); margin-top: -3px; opacity: 0.01;"></div><div aria-hidden="false" aria-live="assertive" aria-atomic="true" style="position: absolute; width: 1px; height: 1px; overflow: hidden; white-space: nowrap; clip: rect(1px, 1px, 1px, 1px); margin-top: -3px; opacity: 0.01;"></div><div aria-hidden="false" aria-live="polite" aria-atomic="true" style="position: absolute; width: 1px; height: 1px; overflow: hidden; white-space: nowrap; clip: rect(1px, 1px, 1px, 1px); margin-top: -3px; opacity: 0.01;"></div><div aria-hidden="false" aria-live="polite" aria-atomic="true" style="position: absolute; width: 1px; height: 1px; overflow: hidden; white-space: nowrap; clip: rect(1px, 1px, 1px, 1px); margin-top: -3px; opacity: 0.01;"></div></div><div id="highcharts-6ffgtg1-6" dir="ltr" style="position: relative; overflow: hidden; width: 626px; height: 400px; text-align: left; line-height: normal; z-index: 0; -webkit-tap-highlight-color: rgba(0, 0, 0, 0); user-select: none; touch-action: manipulation; outline: none; padding: 0px;" class="highcharts-container " aria-hidden="false" tabindex="0"><div aria-hidden="false" class="highcharts-a11y-proxy-container-before" style="top: 0px; left: 0px; white-space: nowrap; position: absolute;"></div><svg version="1.1" class="highcharts-root" style="font-family: -apple-system, BlinkMacSystemFont, &quot;Segoe UI&quot;, Roboto, Helvetica, Arial, &quot;Apple Color Emoji&quot;, &quot;Segoe UI Emoji&quot;, &quot;Segoe UI Symbol&quot;, sans-serif; font-size: 1rem;" xmlns="http://www.w3.org/2000/svg" width="626" height="400" viewBox="0 0 626 400" aria-hidden="false" aria-label="Interactive chart"><desc aria-hidden="true">Created with Highcharts 12.2.0</desc><defs aria-hidden="true"><filter id="highcharts-drop-shadow-1"><feDropShadow dx="1" dy="1" flood-color="#000000" flood-opacity="0.75" stdDeviation="2.5"></feDropShadow></filter><clipPath id="highcharts-6ffgtg1-16-"><rect x="0" y="0" width="543" height="303" fill="none"></rect></clipPath></defs><rect fill="#ffffff" class="highcharts-background" filter="none" x="0" y="0" width="626" height="400" rx="0" ry="0" aria-hidden="true"></rect><rect fill="none" class="highcharts-plot-background" x="73" y="10" width="543" height="303" filter="none" aria-hidden="true"></rect><g class="highcharts-pane-group" data-z-index="0" aria-hidden="true"></g><g class="highcharts-grid highcharts-xaxis-grid" data-z-index="1" aria-hidden="true"><path fill="none" stroke="#e6e6e6" stroke-width="0" stroke-dasharray="none" data-z-index="1" class="highcharts-grid-line" d="M 163.5 10 L 163.5 313" opacity="1"></path><path fill="none" stroke="#e6e6e6" stroke-width="0" stroke-dasharray="none" data-z-index="1" class="highcharts-grid-line" d="M 254.5 10 L 254.5 313" opacity="1"></path><path fill="none" stroke="#e6e6e6" stroke-width="0" stroke-dasharray="none" data-z-index="1" class="highcharts-grid-line" d="M 344.5 10 L 344.5 313" opacity="1"></path><path fill="none" stroke="#e6e6e6" stroke-width="0" stroke-dasharray="none" data-z-index="1" class="highcharts-grid-line" d="M 435.5 10 L 435.5 313" opacity="1"></path><path fill="none" stroke="#e6e6e6" stroke-width="0" stroke-dasharray="none" data-z-index="1" class="highcharts-grid-line" d="M 525.5 10 L 525.5 313" opacity="1"></path><path fill="none" stroke="#e6e6e6" stroke-width="0" stroke-dasharray="none" data-z-index="1" class="highcharts-grid-line" d="M 616.5 10 L 616.5 313" opacity="1"></path><path fill="none" stroke="#e6e6e6" stroke-width="0" stroke-dasharray="none" data-z-index="1" class="highcharts-grid-line" d="M 73.5 10 L 73.5 313" opacity="1"></path></g><g class="highcharts-grid highcharts-yaxis-grid" data-z-index="1" aria-hidden="true"><path fill="none" stroke="#e6e6e6" stroke-width="1" stroke-dasharray="none" data-z-index="1" class="highcharts-grid-line" d="M 73 313.5 L 616 313.5" opacity="1"></path><path fill="none" stroke="#e6e6e6" stroke-width="1" stroke-dasharray="none" data-z-index="1" class="highcharts-grid-line" d="M 73 262.5 L 616 262.5" opacity="1"></path><path fill="none" stroke="#e6e6e6" stroke-width="1" stroke-dasharray="none" data-z-index="1" class="highcharts-grid-line" d="M 73 212.5 L 616 212.5" opacity="1"></path><path fill="none" stroke="#e6e6e6" stroke-width="1" stroke-dasharray="none" data-z-index="1" class="highcharts-grid-line" d="M 73 161.5 L 616 161.5" opacity="1"></path><path fill="none" stroke="#e6e6e6" stroke-width="1" stroke-dasharray="none" data-z-index="1" class="highcharts-grid-line" d="M 73 111.5 L 616 111.5" opacity="1"></path><path fill="none" stroke="#e6e6e6" stroke-width="1" stroke-dasharray="none" data-z-index="1" class="highcharts-grid-line" d="M 73 60.5 L 616 60.5" opacity="1"></path><path fill="none" stroke="#e6e6e6" stroke-width="1" stroke-dasharray="none" data-z-index="1" class="highcharts-grid-line" d="M 73 10.5 L 616 10.5" opacity="1"></path></g><rect fill="none" class="highcharts-plot-border" data-z-index="1" stroke="#cccccc" stroke-width="0" x="73" y="10" width="543" height="303" aria-hidden="true"></rect><g class="highcharts-axis highcharts-xaxis" data-z-index="2" aria-hidden="true"><path fill="none" class="highcharts-axis-line" stroke="#333333" stroke-width="1" data-z-index="7" d="M 73 313.5 L 616 313.5"></path></g><g class="highcharts-axis highcharts-yaxis" data-z-index="2" aria-hidden="true"><text x="24.625" data-z-index="7" text-anchor="middle" transform="translate(0,0) rotate(270 24.625 161.5)" class="highcharts-axis-title" style="font-size: 0.8em; fill: rgb(102, 102, 102);" y="161.5">Total Staff</text><path fill="none" class="highcharts-axis-line" stroke="#333333" stroke-width="0" data-z-index="7" d="M 73 10 L 73 313"></path></g><g class="highcharts-series-group" data-z-index="3" filter="none" aria-hidden="false"><g class="highcharts-series highcharts-series-0 highcharts-column-series highcharts-color-0 highcharts-tracker" data-z-index="0.1" opacity="1" transform="translate(73,10) scale(1 1)" clip-path="url(#highcharts-6ffgtg1-16-)" aria-hidden="false"><path fill="#28a745" d="M 32 269 L 59 269 A 3 3 0 0 1 62 272 L 62 303 A 0 0 0 0 1 62 303 L 29 303 A 0 0 0 0 1 29 303 L 29 272 A 3 3 0 0 1 32 269 Z" stroke="#ffffff" stroke-width="0" opacity="1" filter="none" class="highcharts-point highcharts-color-0" tabindex="-1" role="img" aria-label="Regular, 34. Contract Types." style="outline: none;"></path><path fill="#28a745" d="M 122 18 L 149 18 A 3 3 0 0 1 152 21 L 152 303 A 0 0 0 0 1 152 303 L 119 303 A 0 0 0 0 1 119 303 L 119 21 A 3 3 0 0 1 122 18 Z" stroke="#ffffff" stroke-width="0" opacity="1" filter="none" class="highcharts-point highcharts-color-0" tabindex="-1" role="img" aria-label="Seconded, 282. Contract Types." style="outline: none;"></path><path fill="#28a745" d="M 213 275 L 240 275 A 3 3 0 0 1 243 278 L 243 303 A 0 0 0 0 1 243 303 L 210 303 A 0 0 0 0 1 210 303 L 210 278 A 3 3 0 0 1 213 275 Z" stroke="#ffffff" stroke-width="0" opacity="1" filter="none" class="highcharts-point highcharts-color-0" tabindex="-1" role="img" aria-label="Fixed Term, 28. Contract Types." style="outline: none;"></path><path fill="#28a745" d="M 303 247 L 330 247 A 3 3 0 0 1 333 250 L 333 303 A 0 0 0 0 1 333 303 L 300 303 A 0 0 0 0 1 300 303 L 300 250 A 3 3 0 0 1 303 247 Z" stroke="#ffffff" stroke-width="0" opacity="1" filter="none" class="highcharts-point highcharts-color-0" tabindex="-1" role="img" aria-label="Consultancy , 55. Contract Types." style="outline: none;"></path><path fill="#28a745" d="M 394 300 L 421 300 A 3 3 0 0 1 424 303 L 424 303 A 0 0 0 0 1 424 303 L 391 303 A 0 0 0 0 1 391 303 L 391 303 A 3 3 0 0 1 394 300 Z" stroke="#ffffff" stroke-width="0" opacity="1" filter="none" class="highcharts-point highcharts-color-0" tabindex="-1" role="img" aria-label="ALD, 3. Contract Types." style="outline: none;"></path><path fill="#28a745" d="M 484 302 L 511 302 A 3 3 0 0 1 513.2360679774998 303 L 513.2360679774998 303 A 0 0 0 0 1 513.2360679774998 303 L 513.2360679774998 303 A 0 0 0 0 1 481.7639320225002 303 L 481.7639320225002 303 A 3 3 0 0 1 484 302 Z" stroke="#ffffff" stroke-width="0" opacity="1" filter="none" class="highcharts-point highcharts-color-0" tabindex="-1" role="img" aria-label="Fellowship, 1. Contract Types." style="outline: none;"></path></g><g class="highcharts-markers highcharts-series-0 highcharts-column-series highcharts-color-0" data-z-index="0.1" opacity="1" transform="translate(73,10) scale(1 1)" clip-path="none" aria-hidden="true"></g></g><g class="highcharts-exporting-group" data-z-index="3" aria-hidden="true"><g class="highcharts-no-tooltip highcharts-button highcharts-contextbutton" stroke-linecap="round" style="cursor: pointer;" transform="translate(588,5)"><title>Chart context menu</title><rect fill="#ffffff" class="highcharts-button-box" x="0.5" y="0.5" width="28" height="28" rx="2" ry="2" stroke="none" stroke-width="1"></rect><path fill="#666666" d="M 8 9.5 L 22 9.5 M 8 14.5 L 22 14.5 M 8 19.5 L 22 19.5" class="highcharts-button-symbol" data-z-index="1" stroke="#666666" stroke-width="3"></path><text x="28" data-z-index="1" y="18.5" text-anchor="end" style="font-size: 0.8em; font-weight: normal; fill: rgb(51, 51, 51);"></text></g></g><text x="313" class="highcharts-title" data-z-index="4" text-align="center" y="25" text-anchor="middle" transform-origin="10 22" transform="translate(0,0) scale(1 1)" style="font-size: 1.2em; font-weight: bold; fill: rgb(51, 51, 51);" aria-hidden="true"></text><text x="313" class="highcharts-subtitle" data-z-index="4" text-align="center" y="24" text-anchor="middle" transform-origin="10 15" transform="translate(0,0) scale(1 1)" style="font-size: 0.8em; fill: rgb(102, 102, 102);" aria-hidden="true"></text><text x="10" text-anchor="start" class="highcharts-caption" data-z-index="4" style="font-size: 0.8em; fill: rgb(102, 102, 102);" text-align="left" y="397" transform-origin="10 15" transform="translate(0,0) scale(1 1)" aria-hidden="true"></text><g class="highcharts-data-labels highcharts-series-0 highcharts-column-series highcharts-color-0 highcharts-tracker" data-z-index="6" opacity="1" transform="translate(73,10) scale(1 1)" aria-hidden="true"><g class="highcharts-label highcharts-data-label highcharts-data-label-color-0" data-z-index="1" filter="none" transform="translate(32,246)"><text x="13.1484375" data-z-index="1" y="16" text-anchor="middle" style="font-size: 0.7em; font-weight: bold; fill: rgb(0, 0, 0);"><tspan class="highcharts-text-outline" fill="#FFFFFF" stroke="#FFFFFF" stroke-width="2px" stroke-linejoin="round" style="">34<tspan x="13.1484375" dy="0">&ZeroWidthSpace;</tspan></tspan>34</text></g><g class="highcharts-label highcharts-data-label highcharts-data-label-color-0" data-z-index="1" filter="none" transform="translate(119,-5)"><text x="16.921875" data-z-index="1" y="16" text-anchor="middle" style="font-size: 0.7em; font-weight: bold; fill: rgb(0, 0, 0);"><tspan class="highcharts-text-outline" fill="#FFFFFF" stroke="#FFFFFF" stroke-width="2px" stroke-linejoin="round" style="">282<tspan x="16.921875" dy="0">&ZeroWidthSpace;</tspan></tspan>282</text></g><g class="highcharts-label highcharts-data-label highcharts-data-label-color-0" data-z-index="1" filter="none" transform="translate(213,252)"><text x="13.1484375" data-z-index="1" y="16" text-anchor="middle" style="font-size: 0.7em; font-weight: bold; fill: rgb(0, 0, 0);"><tspan class="highcharts-text-outline" fill="#FFFFFF" stroke="#FFFFFF" stroke-width="2px" stroke-linejoin="round">28<tspan x="13.1484375" dy="0">&ZeroWidthSpace;</tspan></tspan>28</text></g><g class="highcharts-label highcharts-data-label highcharts-data-label-color-0" data-z-index="1" filter="none" transform="translate(303,224)"><text x="13.1484375" data-z-index="1" y="16" text-anchor="middle" style="font-size: 0.7em; font-weight: bold; fill: rgb(0, 0, 0);"><tspan class="highcharts-text-outline" fill="#FFFFFF" stroke="#FFFFFF" stroke-width="2px" stroke-linejoin="round">55<tspan x="13.1484375" dy="0">&ZeroWidthSpace;</tspan></tspan>55</text></g><g class="highcharts-label highcharts-data-label highcharts-data-label-color-0" data-z-index="1" filter="none" transform="translate(398,277)"><text x="9.0234375" data-z-index="1" y="16" text-anchor="middle" style="font-size: 0.7em; font-weight: bold; fill: rgb(0, 0, 0);"><tspan class="highcharts-text-outline" fill="#FFFFFF" stroke="#FFFFFF" stroke-width="2px" stroke-linejoin="round" style="">3<tspan x="9.0234375" dy="0">&ZeroWidthSpace;</tspan></tspan>3</text></g><g class="highcharts-label highcharts-data-label highcharts-data-label-color-0" data-z-index="1" filter="none" transform="translate(488,279)"><text x="9.0234375" data-z-index="1" y="16" text-anchor="middle" style="font-size: 0.7em; font-weight: bold; fill: rgb(0, 0, 0);"><tspan class="highcharts-text-outline" fill="#FFFFFF" stroke="#FFFFFF" stroke-width="2px" stroke-linejoin="round">1<tspan x="9.0234375" dy="0">&ZeroWidthSpace;</tspan></tspan>1</text></g></g><g class="highcharts-legend highcharts-no-tooltip" data-z-index="7" text-align="center" transform="translate(245,355)" aria-hidden="true"><rect fill="none" class="highcharts-legend-box" rx="0" ry="0" stroke="#999999" stroke-width="0" filter="none" x="0" y="0" width="136" height="30"></rect><g data-z-index="1"><g><g class="highcharts-legend-item highcharts-column-series highcharts-color-0 highcharts-series-0" data-z-index="1" transform="translate(8,3)"><text x="21" text-anchor="start" data-z-index="2" style="cursor: pointer; font-size: 0.8em; text-decoration: none; fill: rgb(51, 51, 51);" y="17">Contract Types</text><rect x="2" y="6" rx="6" ry="6" width="12" height="12" fill="#28a745" class="highcharts-point" data-z-index="3"></rect></g></g></g></g><g class="highcharts-axis-labels highcharts-xaxis-labels" data-z-index="7" aria-hidden="true"><text x="118.25" text-anchor="middle" transform="translate(0,0)" style="cursor: default; font-size: 0.8em; fill: rgb(51, 51, 51);" y="340" opacity="1">Regular</text><text x="208.75" text-anchor="middle" transform="translate(0,0)" style="cursor: default; font-size: 0.8em; fill: rgb(51, 51, 51);" y="340" opacity="1">Seconded</text><text x="299.25" text-anchor="middle" transform="translate(0,0)" style="cursor: default; font-size: 0.8em; fill: rgb(51, 51, 51);" y="340" opacity="1">Fixed Term</text><text x="389.75" text-anchor="middle" transform="translate(0,0)" style="cursor: default; font-size: 0.8em; fill: rgb(51, 51, 51);" y="340" opacity="1">Consultancy</text><text x="480.25" text-anchor="middle" transform="translate(0,0)" style="cursor: default; font-size: 0.8em; fill: rgb(51, 51, 51);" y="340" opacity="1">ALD</text><text x="570.75" text-anchor="middle" transform="translate(0,0)" style="cursor: default; font-size: 0.8em; fill: rgb(51, 51, 51);" y="340" opacity="1">Fellowship</text></g><g class="highcharts-axis-labels highcharts-yaxis-labels" data-z-index="7" aria-hidden="true"><text x="58" text-anchor="end" transform="translate(0,0)" style="cursor: default; font-size: 0.8em; fill: rgb(51, 51, 51);" y="318" opacity="1">0</text><text x="58" text-anchor="end" transform="translate(0,0)" style="cursor: default; font-size: 0.8em; fill: rgb(51, 51, 51);" y="267" opacity="1">50</text><text x="58" text-anchor="end" transform="translate(0,0)" style="cursor: default; font-size: 0.8em; fill: rgb(51, 51, 51);" y="217" opacity="1">100</text><text x="58" text-anchor="end" transform="translate(0,0)" style="cursor: default; font-size: 0.8em; fill: rgb(51, 51, 51);" y="166" opacity="1">150</text><text x="58" text-anchor="end" transform="translate(0,0)" style="cursor: default; font-size: 0.8em; fill: rgb(51, 51, 51);" y="116" opacity="1">200</text><text x="58" text-anchor="end" transform="translate(0,0)" style="cursor: default; font-size: 0.8em; fill: rgb(51, 51, 51);" y="65" opacity="1">250</text><text x="58" text-anchor="end" transform="translate(0,0)" style="cursor: default; font-size: 0.8em; fill: rgb(51, 51, 51);" y="15" opacity="1">300</text></g></svg><div aria-hidden="false" class="highcharts-a11y-proxy-container-after" style="top: 0px; left: 0px; white-space: nowrap; position: absolute;"><div class="highcharts-a11y-proxy-group highcharts-a11y-proxy-group-zoom"></div><div class="highcharts-a11y-proxy-group highcharts-a11y-proxy-group-legend" aria-label="Toggle series visibility, Chart" role="region"><ul role="list"><li style="list-style: none;"><button class="highcharts-a11y-proxy-element" tabindex="-1" aria-pressed="true" aria-label="Show Contract Types" style="border-width: 0px; background-color: transparent; cursor: pointer; outline: none; opacity: 0.001; z-index: 999; overflow: hidden; padding: 0px; margin: 0px; display: block; position: absolute; width: 117.523px; height: 15px; left: 255px; top: 363px;"></button></li></ul></div><div class="highcharts-a11y-proxy-group highcharts-a11y-proxy-group-chartMenu"><button class="highcharts-a11y-proxy-element highcharts-no-tooltip" aria-label="View chart menu, Chart" aria-expanded="false" title="Chart context menu" style="border-width: 0px; background-color: transparent; cursor: pointer; outline: none; opacity: 0.001; z-index: 999; overflow: hidden; padding: 0px; margin: 0px; display: block; position: absolute; width: 28px; height: 28px; left: 589px; top: 6px;"></button></div></div></div><div id="highcharts-screen-reader-region-after-1" aria-hidden="false" style="position: relative;"><div aria-hidden="false" style="position: absolute; width: 1px; height: 1px; overflow: hidden; white-space: nowrap; clip: rect(1px, 1px, 1px, 1px); margin-top: -3px; opacity: 0.01;"><div id="highcharts-end-of-chart-marker-1" class="highcharts-exit-anchor" tabindex="0" aria-hidden="false">End of interactive chart.</div></div></div></div>
          </figure>
        </div>
      </div>
    </div>
  </div>
  
</div>
<!--end row-->

<div class="row">
 <!-- //--- -->
  <div class="col-12 col-lg-12 d-flex">
    <div class="card rounded-1 w-100">
      <div class="card-body">
        <div class="d-flex align-items-center">
          <div>
            <h6 class="mb-0">Staff by Division</h6>
          </div>
        </div>
        <div>
          <figure class="highcharts-figure">
            <div id="container4" data-highcharts-chart="2" aria-hidden="false" role="region" aria-label="Chart. Highcharts interactive chart." style="overflow: hidden;"><div id="highcharts-screen-reader-region-before-2" style="position: relative;" aria-hidden="false"><div aria-hidden="false" style="position: absolute; width: 1px; height: 1px; overflow: hidden; white-space: nowrap; clip: rect(1px, 1px, 1px, 1px); margin-top: -3px; opacity: 0.01;"><h6>Chart</h6><div>Bar chart with 28 bars.</div><div><button id="hc-linkto-highcharts-data-table-2" tabindex="-1" aria-expanded="false">View as data table, Chart</button></div><div>The chart has 1 X axis displaying categories. </div><div>The chart has 1 Y axis displaying Total Staff. Data ranges from 2 to 34.</div></div></div><div aria-hidden="false" class="highcharts-announcer-container" style="position: relative;"><div aria-hidden="false" aria-live="polite" aria-atomic="true" style="position: absolute; width: 1px; height: 1px; overflow: hidden; white-space: nowrap; clip: rect(1px, 1px, 1px, 1px); margin-top: -3px; opacity: 0.01;"></div><div aria-hidden="false" aria-live="assertive" aria-atomic="true" style="position: absolute; width: 1px; height: 1px; overflow: hidden; white-space: nowrap; clip: rect(1px, 1px, 1px, 1px); margin-top: -3px; opacity: 0.01;"></div><div aria-hidden="false" aria-live="polite" aria-atomic="true" style="position: absolute; width: 1px; height: 1px; overflow: hidden; white-space: nowrap; clip: rect(1px, 1px, 1px, 1px); margin-top: -3px; opacity: 0.01;"></div><div aria-hidden="false" aria-live="polite" aria-atomic="true" style="position: absolute; width: 1px; height: 1px; overflow: hidden; white-space: nowrap; clip: rect(1px, 1px, 1px, 1px); margin-top: -3px; opacity: 0.01;"></div></div><div id="highcharts-6ffgtg1-17" dir="ltr" style="position: relative; overflow: hidden; width: 1308px; height: 400px; text-align: left; line-height: normal; z-index: 0; -webkit-tap-highlight-color: rgba(0, 0, 0, 0); user-select: none; touch-action: manipulation; outline: none; padding: 0px;" class="highcharts-container " aria-hidden="false" tabindex="0"><div aria-hidden="false" class="highcharts-a11y-proxy-container-before" style="top: 0px; left: 0px; white-space: nowrap; position: absolute;"></div><svg version="1.1" class="highcharts-root" style="font-family: -apple-system, BlinkMacSystemFont, &quot;Segoe UI&quot;, Roboto, Helvetica, Arial, &quot;Apple Color Emoji&quot;, &quot;Segoe UI Emoji&quot;, &quot;Segoe UI Symbol&quot;, sans-serif; font-size: 1rem;" xmlns="http://www.w3.org/2000/svg" width="1308" height="400" viewBox="0 0 1308 400" aria-hidden="false" aria-label="Interactive chart"><desc aria-hidden="true">Created with Highcharts 12.2.0</desc><defs aria-hidden="true"><filter id="highcharts-drop-shadow-2"><feDropShadow dx="1" dy="1" flood-color="#000000" flood-opacity="0.75" stdDeviation="2.5"></feDropShadow></filter><clipPath id="highcharts-6ffgtg1-49-"><rect x="0" y="0" width="1233" height="213" fill="none"></rect></clipPath></defs><rect fill="#ffffff" class="highcharts-background" filter="none" x="0" y="0" width="1308" height="400" rx="0" ry="0" aria-hidden="true"></rect><rect fill="none" class="highcharts-plot-background" x="65" y="10" width="1233" height="213" filter="none" aria-hidden="true"></rect><g class="highcharts-pane-group" data-z-index="0" aria-hidden="true"></g><g class="highcharts-grid highcharts-xaxis-grid" data-z-index="1" aria-hidden="true"><path fill="none" stroke="#e6e6e6" stroke-width="0" stroke-dasharray="none" data-z-index="1" class="highcharts-grid-line" d="M 109.5 10 L 109.5 223" opacity="1"></path><path fill="none" stroke="#e6e6e6" stroke-width="0" stroke-dasharray="none" data-z-index="1" class="highcharts-grid-line" d="M 153.5 10 L 153.5 223" opacity="1"></path><path fill="none" stroke="#e6e6e6" stroke-width="0" stroke-dasharray="none" data-z-index="1" class="highcharts-grid-line" d="M 197.5 10 L 197.5 223" opacity="1"></path><path fill="none" stroke="#e6e6e6" stroke-width="0" stroke-dasharray="none" data-z-index="1" class="highcharts-grid-line" d="M 241.5 10 L 241.5 223" opacity="1"></path><path fill="none" stroke="#e6e6e6" stroke-width="0" stroke-dasharray="none" data-z-index="1" class="highcharts-grid-line" d="M 285.5 10 L 285.5 223" opacity="1"></path><path fill="none" stroke="#e6e6e6" stroke-width="0" stroke-dasharray="none" data-z-index="1" class="highcharts-grid-line" d="M 329.5 10 L 329.5 223" opacity="1"></path><path fill="none" stroke="#e6e6e6" stroke-width="0" stroke-dasharray="none" data-z-index="1" class="highcharts-grid-line" d="M 373.5 10 L 373.5 223" opacity="1"></path><path fill="none" stroke="#e6e6e6" stroke-width="0" stroke-dasharray="none" data-z-index="1" class="highcharts-grid-line" d="M 417.5 10 L 417.5 223" opacity="1"></path><path fill="none" stroke="#e6e6e6" stroke-width="0" stroke-dasharray="none" data-z-index="1" class="highcharts-grid-line" d="M 461.5 10 L 461.5 223" opacity="1"></path><path fill="none" stroke="#e6e6e6" stroke-width="0" stroke-dasharray="none" data-z-index="1" class="highcharts-grid-line" d="M 505.5 10 L 505.5 223" opacity="1"></path><path fill="none" stroke="#e6e6e6" stroke-width="0" stroke-dasharray="none" data-z-index="1" class="highcharts-grid-line" d="M 549.5 10 L 549.5 223" opacity="1"></path><path fill="none" stroke="#e6e6e6" stroke-width="0" stroke-dasharray="none" data-z-index="1" class="highcharts-grid-line" d="M 593.5 10 L 593.5 223" opacity="1"></path><path fill="none" stroke="#e6e6e6" stroke-width="0" stroke-dasharray="none" data-z-index="1" class="highcharts-grid-line" d="M 637.5 10 L 637.5 223" opacity="1"></path><path fill="none" stroke="#e6e6e6" stroke-width="0" stroke-dasharray="none" data-z-index="1" class="highcharts-grid-line" d="M 681.5 10 L 681.5 223" opacity="1"></path><path fill="none" stroke="#e6e6e6" stroke-width="0" stroke-dasharray="none" data-z-index="1" class="highcharts-grid-line" d="M 725.5 10 L 725.5 223" opacity="1"></path><path fill="none" stroke="#e6e6e6" stroke-width="0" stroke-dasharray="none" data-z-index="1" class="highcharts-grid-line" d="M 769.5 10 L 769.5 223" opacity="1"></path><path fill="none" stroke="#e6e6e6" stroke-width="0" stroke-dasharray="none" data-z-index="1" class="highcharts-grid-line" d="M 813.5 10 L 813.5 223" opacity="1"></path><path fill="none" stroke="#e6e6e6" stroke-width="0" stroke-dasharray="none" data-z-index="1" class="highcharts-grid-line" d="M 857.5 10 L 857.5 223" opacity="1"></path><path fill="none" stroke="#e6e6e6" stroke-width="0" stroke-dasharray="none" data-z-index="1" class="highcharts-grid-line" d="M 901.5 10 L 901.5 223" opacity="1"></path><path fill="none" stroke="#e6e6e6" stroke-width="0" stroke-dasharray="none" data-z-index="1" class="highcharts-grid-line" d="M 945.5 10 L 945.5 223" opacity="1"></path><path fill="none" stroke="#e6e6e6" stroke-width="0" stroke-dasharray="none" data-z-index="1" class="highcharts-grid-line" d="M 989.5 10 L 989.5 223" opacity="1"></path><path fill="none" stroke="#e6e6e6" stroke-width="0" stroke-dasharray="none" data-z-index="1" class="highcharts-grid-line" d="M 1033.5 10 L 1033.5 223" opacity="1"></path><path fill="none" stroke="#e6e6e6" stroke-width="0" stroke-dasharray="none" data-z-index="1" class="highcharts-grid-line" d="M 1077.5 10 L 1077.5 223" opacity="1"></path><path fill="none" stroke="#e6e6e6" stroke-width="0" stroke-dasharray="none" data-z-index="1" class="highcharts-grid-line" d="M 1121.5 10 L 1121.5 223" opacity="1"></path><path fill="none" stroke="#e6e6e6" stroke-width="0" stroke-dasharray="none" data-z-index="1" class="highcharts-grid-line" d="M 1165.5 10 L 1165.5 223" opacity="1"></path><path fill="none" stroke="#e6e6e6" stroke-width="0" stroke-dasharray="none" data-z-index="1" class="highcharts-grid-line" d="M 1209.5 10 L 1209.5 223" opacity="1"></path><path fill="none" stroke="#e6e6e6" stroke-width="0" stroke-dasharray="none" data-z-index="1" class="highcharts-grid-line" d="M 1253.5 10 L 1253.5 223" opacity="1"></path><path fill="none" stroke="#e6e6e6" stroke-width="0" stroke-dasharray="none" data-z-index="1" class="highcharts-grid-line" d="M 1298.5 10 L 1298.5 223" opacity="1"></path><path fill="none" stroke="#e6e6e6" stroke-width="0" stroke-dasharray="none" data-z-index="1" class="highcharts-grid-line" d="M 65.5 10 L 65.5 223" opacity="1"></path></g><g class="highcharts-grid highcharts-yaxis-grid" data-z-index="1" aria-hidden="true"><path fill="none" stroke="#e6e6e6" stroke-width="1" stroke-dasharray="none" data-z-index="1" class="highcharts-grid-line" d="M 65 223.5 L 1298 223.5" opacity="1"></path><path fill="none" stroke="#e6e6e6" stroke-width="1" stroke-dasharray="none" data-z-index="1" class="highcharts-grid-line" d="M 65 169.5 L 1298 169.5" opacity="1"></path><path fill="none" stroke="#e6e6e6" stroke-width="1" stroke-dasharray="none" data-z-index="1" class="highcharts-grid-line" d="M 65 116.5 L 1298 116.5" opacity="1"></path><path fill="none" stroke="#e6e6e6" stroke-width="1" stroke-dasharray="none" data-z-index="1" class="highcharts-grid-line" d="M 65 63.5 L 1298 63.5" opacity="1"></path><path fill="none" stroke="#e6e6e6" stroke-width="1" stroke-dasharray="none" data-z-index="1" class="highcharts-grid-line" d="M 65 10.5 L 1298 10.5" opacity="1"></path></g><rect fill="none" class="highcharts-plot-border" data-z-index="1" stroke="#cccccc" stroke-width="0" x="65" y="10" width="1233" height="213" aria-hidden="true"></rect><g class="highcharts-axis highcharts-xaxis" data-z-index="2" aria-hidden="true"><path fill="none" class="highcharts-axis-line" stroke="#333333" stroke-width="1" data-z-index="7" d="M 65 223.5 L 1298 223.5"></path></g><g class="highcharts-axis highcharts-yaxis" data-z-index="2" aria-hidden="true"><text x="25.125" data-z-index="7" text-anchor="middle" transform="translate(0,0) rotate(270 25.125 116.5)" class="highcharts-axis-title" style="font-size: 0.8em; fill: rgb(102, 102, 102);" y="116.5">Total Staff</text><path fill="none" class="highcharts-axis-line" stroke="#333333" stroke-width="0" data-z-index="7" d="M 65 10 L 65 223"></path></g><g class="highcharts-series-group" data-z-index="3" filter="none" aria-hidden="false"><g class="highcharts-series highcharts-series-0 highcharts-column-series highcharts-color-0 highcharts-tracker" data-z-index="0.1" opacity="1" transform="translate(65,10) scale(1 1)" clip-path="url(#highcharts-6ffgtg1-49-)" aria-hidden="false"><path fill="#28a745" d="M 12 53 L 32 53 A 3 3 0 0 1 35 56 L 35 213 A 0 0 0 0 1 35 213 L 9 213 A 0 0 0 0 1 9 213 L 9 56 A 3 3 0 0 1 12 53 Z" stroke="#ffffff" stroke-width="0" opacity="1" filter="none" class="highcharts-point highcharts-color-0" tabindex="-1" role="img" aria-label="Directorate of Administration , 30. Divisions." style="outline: none;"></path><path fill="#28a745" d="M 56 192 L 76 192 A 3 3 0 0 1 79 195 L 79 213 A 0 0 0 0 1 79 213 L 53 213 A 0 0 0 0 1 53 213 L 53 195 A 3 3 0 0 1 56 192 Z" stroke="#ffffff" stroke-width="0" opacity="1" filter="none" class="highcharts-point highcharts-color-0" tabindex="-1" role="img" aria-label="Policy and Health Diplomacy, 4. Divisions." style="outline: none;"></path><path fill="#28a745" d="M 100 59 L 120 59 A 3 3 0 0 1 123 62 L 123 213 A 0 0 0 0 1 123 213 L 97 213 A 0 0 0 0 1 97 213 L 97 62 A 3 3 0 0 1 100 59 Z" stroke="#ffffff" stroke-width="0" opacity="1" filter="none" class="highcharts-point highcharts-color-0" tabindex="-1" role="img" aria-label="Centre for Primary Healthcare, 29. Divisions." style="outline: none;"></path><path fill="#28a745" d="M 144 85 L 164 85 A 3 3 0 0 1 167 88 L 167 213 A 0 0 0 0 1 167 213 L 141 213 A 0 0 0 0 1 141 213 L 141 88 A 3 3 0 0 1 144 85 Z" stroke="#ffffff" stroke-width="0" opacity="1" filter="none" class="highcharts-point highcharts-color-0" tabindex="-1" role="img" aria-label="Executive Office, 24. Divisions." style="outline: none;"></path><path fill="#28a745" d="M 188 202 L 208 202 A 3 3 0 0 1 211 205 L 211 213 A 0 0 0 0 1 211 213 L 185 213 A 0 0 0 0 1 185 213 L 185 205 A 3 3 0 0 1 188 202 Z" stroke="#ffffff" stroke-width="0" opacity="1" filter="none" class="highcharts-point highcharts-color-0" tabindex="-1" role="img" aria-label="Office of the Director General, 2. Divisions." style="outline: none;"></path><path fill="#28a745" d="M 232 128 L 252 128 A 3 3 0 0 1 255 131 L 255 213 A 0 0 0 0 1 255 213 L 229 213 A 0 0 0 0 1 229 213 L 229 131 A 3 3 0 0 1 232 128 Z" stroke="#ffffff" stroke-width="0" opacity="1" filter="none" class="highcharts-point highcharts-color-0" tabindex="-1" role="img" aria-label="Public Health Institutes and Research, 16. Divisions." style="outline: none;"></path><path fill="#28a745" d="M 276 202 L 296 202 A 3 3 0 0 1 299 205 L 299 213 A 0 0 0 0 1 299 213 L 273 213 A 0 0 0 0 1 273 213 L 273 205 A 3 3 0 0 1 276 202 Z" stroke="#ffffff" stroke-width="0" opacity="1" filter="none" class="highcharts-point highcharts-color-0" tabindex="-1" role="img" aria-label="Office of the Deputy Director General, 2. Divisions." style="outline: none;"></path><path fill="#28a745" d="M 320 96 L 340 96 A 3 3 0 0 1 343 99 L 343 213 A 0 0 0 0 1 343 213 L 317 213 A 0 0 0 0 1 317 213 L 317 99 A 3 3 0 0 1 320 96 Z" stroke="#ffffff" stroke-width="0" opacity="1" filter="none" class="highcharts-point highcharts-color-0" tabindex="-1" role="img" aria-label="Southern RCC, 22. Divisions." style="outline: none;"></path><path fill="#28a745" d="M 364 144 L 385 144 A 3 3 0 0 1 388 147 L 388 213 A 0 0 0 0 1 388 213 L 361 213 A 0 0 0 0 1 361 213 L 361 147 A 3 3 0 0 1 364 144 Z" stroke="#ffffff" stroke-width="0" opacity="1" filter="none" class="highcharts-point highcharts-color-0" tabindex="-1" role="img" aria-label="Directorate of Finance, 13. Divisions." style="outline: none;"></path><path fill="#28a745" d="M 408 133 L 429 133 A 3 3 0 0 1 432 136 L 432 213 A 0 0 0 0 1 432 213 L 405 213 A 0 0 0 0 1 405 213 L 405 136 A 3 3 0 0 1 408 133 Z" stroke="#ffffff" stroke-width="0" opacity="1" filter="none" class="highcharts-point highcharts-color-0" tabindex="-1" role="img" aria-label="Planning Reporting and Accountability, 15. Divisions." style="outline: none;"></path><path fill="#28a745" d="M 452 128 L 473 128 A 3 3 0 0 1 476 131 L 476 213 A 0 0 0 0 1 476 213 L 449 213 A 0 0 0 0 1 449 213 L 449 131 A 3 3 0 0 1 452 128 Z" stroke="#ffffff" stroke-width="0" opacity="1" filter="none" class="highcharts-point highcharts-color-0" tabindex="-1" role="img" aria-label="Surveillance and Disease Intelligence , 16. Divisions." style="outline: none;"></path><path fill="#28a745" d="M 496 192 L 517 192 A 3 3 0 0 1 520 195 L 520 213 A 0 0 0 0 1 520 213 L 493 213 A 0 0 0 0 1 493 213 L 493 195 A 3 3 0 0 1 496 192 Z" stroke="#ffffff" stroke-width="0" opacity="1" filter="none" class="highcharts-point highcharts-color-0" tabindex="-1" role="img" aria-label="Health Economics and Financing, 4. Divisions." style="outline: none;"></path><path fill="#28a745" d="M 540 186 L 561 186 A 3 3 0 0 1 564 189 L 564 213 A 0 0 0 0 1 564 213 L 537 213 A 0 0 0 0 1 537 213 L 537 189 A 3 3 0 0 1 540 186 Z" stroke="#ffffff" stroke-width="0" opacity="1" filter="none" class="highcharts-point highcharts-color-0" tabindex="-1" role="img" aria-label="Supply Chain Management, 5. Divisions." style="outline: none;"></path><path fill="#28a745" d="M 584 96 L 605 96 A 3 3 0 0 1 608 99 L 608 213 A 0 0 0 0 1 608 213 L 581 213 A 0 0 0 0 1 581 213 L 581 99 A 3 3 0 0 1 584 96 Z" stroke="#ffffff" stroke-width="0" opacity="1" filter="none" class="highcharts-point highcharts-color-0" tabindex="-1" role="img" aria-label="Emergency Preparedness and Response, 22. Divisions." style="outline: none;"></path><path fill="#28a745" d="M 628 186 L 649 186 A 3 3 0 0 1 652 189 L 652 213 A 0 0 0 0 1 652 213 L 625 213 A 0 0 0 0 1 625 213 L 625 189 A 3 3 0 0 1 628 186 Z" stroke="#ffffff" stroke-width="0" opacity="1" filter="none" class="highcharts-point highcharts-color-0" tabindex="-1" role="img" aria-label="Local Manufacturing of Health Commodities, 5. Divisions." style="outline: none;"></path><path fill="#28a745" d="M 672 181 L 693 181 A 3 3 0 0 1 696 184 L 696 213 A 0 0 0 0 1 696 213 L 669 213 A 0 0 0 0 1 669 213 L 669 184 A 3 3 0 0 1 672 181 Z" stroke="#ffffff" stroke-width="0" opacity="1" filter="none" class="highcharts-point highcharts-color-0" tabindex="-1" role="img" aria-label="Legal Affairs and Dispute Settlement, 6. Divisions." style="outline: none;"></path><path fill="#28a745" d="M 716 128 L 737 128 A 3 3 0 0 1 740 131 L 740 213 A 0 0 0 0 1 740 213 L 713 213 A 0 0 0 0 1 713 213 L 713 131 A 3 3 0 0 1 716 128 Z" stroke="#ffffff" stroke-width="0" opacity="1" filter="none" class="highcharts-point highcharts-color-0" tabindex="-1" role="img" aria-label="Laboratory Networks and Systems, 16. Divisions." style="outline: none;"></path><path fill="#28a745" d="M 760 202 L 781 202 A 3 3 0 0 1 784 205 L 784 213 A 0 0 0 0 1 784 213 L 757 213 A 0 0 0 0 1 757 213 L 757 205 A 3 3 0 0 1 760 202 Z" stroke="#ffffff" stroke-width="0" opacity="1" filter="none" class="highcharts-point highcharts-color-0" tabindex="-1" role="img" aria-label="Disease Control and Prevention, 2. Divisions." style="outline: none;"></path><path fill="#28a745" d="M 804 165 L 825 165 A 3 3 0 0 1 828 168 L 828 213 A 0 0 0 0 1 828 213 L 801 213 A 0 0 0 0 1 801 213 L 801 168 A 3 3 0 0 1 804 165 Z" stroke="#ffffff" stroke-width="0" opacity="1" filter="none" class="highcharts-point highcharts-color-0" tabindex="-1" role="img" aria-label="PIU, 9. Divisions." style="outline: none;"></path><path fill="#28a745" d="M 848 91 L 869 91 A 3 3 0 0 1 872 94 L 872 213 A 0 0 0 0 1 872 213 L 845 213 A 0 0 0 0 1 845 213 L 845 94 A 3 3 0 0 1 848 91 Z" stroke="#ffffff" stroke-width="0" opacity="1" filter="none" class="highcharts-point highcharts-color-0" tabindex="-1" role="img" aria-label="Central RCC, 23. Divisions." style="outline: none;"></path><path fill="#28a745" d="M 893 32 L 913 32 A 3 3 0 0 1 916 35 L 916 213 A 0 0 0 0 1 916 213 L 890 213 A 0 0 0 0 1 890 213 L 890 35 A 3 3 0 0 1 893 32 Z" stroke="#ffffff" stroke-width="0" opacity="1" filter="none" class="highcharts-point highcharts-color-0" tabindex="-1" role="img" aria-label="Eastern RCC, 34. Divisions." style="outline: none;"></path><path fill="#28a745" d="M 937 128 L 957 128 A 3 3 0 0 1 960 131 L 960 213 A 0 0 0 0 1 960 213 L 934 213 A 0 0 0 0 1 934 213 L 934 131 A 3 3 0 0 1 937 128 Z" stroke="#ffffff" stroke-width="0" opacity="1" filter="none" class="highcharts-point highcharts-color-0" tabindex="-1" role="img" aria-label="Directorate of Communication and Public Information, 16. Divisions." style="outline: none;"></path><path fill="#28a745" d="M 981 160 L 1001 160 A 3 3 0 0 1 1004 163 L 1004 213 A 0 0 0 0 1 1004 213 L 978 213 A 0 0 0 0 1 978 213 L 978 163 A 3 3 0 0 1 981 160 Z" stroke="#ffffff" stroke-width="0" opacity="1" filter="none" class="highcharts-point highcharts-color-0" tabindex="-1" role="img" aria-label="Directorate of Science and Innovation, 10. Divisions." style="outline: none;"></path><path fill="#28a745" d="M 1025 154 L 1045 154 A 3 3 0 0 1 1048 157 L 1048 213 A 0 0 0 0 1 1048 213 L 1022 213 A 0 0 0 0 1 1022 213 L 1022 157 A 3 3 0 0 1 1025 154 Z" stroke="#ffffff" stroke-width="0" opacity="1" filter="none" class="highcharts-point highcharts-color-0" tabindex="-1" role="img" aria-label="Digital Health and Information Systems, 11. Divisions." style="outline: none;"></path><path fill="#28a745" d="M 1069 64 L 1089 64 A 3 3 0 0 1 1092 67 L 1092 213 A 0 0 0 0 1 1092 213 L 1066 213 A 0 0 0 0 1 1066 213 L 1066 67 A 3 3 0 0 1 1069 64 Z" stroke="#ffffff" stroke-width="0" opacity="1" filter="none" class="highcharts-point highcharts-color-0" tabindex="-1" role="img" aria-label="Western RCC, 28. Divisions." style="outline: none;"></path><path fill="#28a745" d="M 1113 170 L 1133 170 A 3 3 0 0 1 1136 173 L 1136 213 A 0 0 0 0 1 1136 213 L 1110 213 A 0 0 0 0 1 1110 213 L 1110 173 A 3 3 0 0 1 1113 170 Z" stroke="#ffffff" stroke-width="0" opacity="1" filter="none" class="highcharts-point highcharts-color-0" tabindex="-1" role="img" aria-label="Directorate of External Relations and Strategic Engagements, 8. Divisions." style="outline: none;"></path><path fill="#28a745" d="M 1157 149 L 1177 149 A 3 3 0 0 1 1180 152 L 1180 213 A 0 0 0 0 1 1180 213 L 1154 213 A 0 0 0 0 1 1154 213 L 1154 152 A 3 3 0 0 1 1157 149 Z" stroke="#ffffff" stroke-width="0" opacity="1" filter="none" class="highcharts-point highcharts-color-0" tabindex="-1" role="img" aria-label="Northern RCC, 12. Divisions." style="outline: none;"></path><path fill="#28a745" d="M 1201 128 L 1221 128 A 3 3 0 0 1 1224 131 L 1224 213 A 0 0 0 0 1 1224 213 L 1198 213 A 0 0 0 0 1 1198 213 L 1198 131 A 3 3 0 0 1 1201 128 Z" stroke="#ffffff" stroke-width="0" opacity="1" filter="none" class="highcharts-point highcharts-color-0" tabindex="-1" role="img" aria-label="IMST - External, 16. Divisions." style="outline: none;"></path></g><g class="highcharts-markers highcharts-series-0 highcharts-column-series highcharts-color-0" data-z-index="0.1" opacity="1" transform="translate(65,10) scale(1 1)" clip-path="none" aria-hidden="true"></g></g><g class="highcharts-exporting-group" data-z-index="3" aria-hidden="true"><g class="highcharts-no-tooltip highcharts-button highcharts-contextbutton" stroke-linecap="round" style="cursor: pointer;" transform="translate(1270,5)"><title>Chart context menu</title><rect fill="#ffffff" class="highcharts-button-box" x="0.5" y="0.5" width="28" height="28" rx="2" ry="2" stroke="none" stroke-width="1"></rect><path fill="#666666" d="M 8 9.5 L 22 9.5 M 8 14.5 L 22 14.5 M 8 19.5 L 22 19.5" class="highcharts-button-symbol" data-z-index="1" stroke="#666666" stroke-width="3"></path><text x="28" data-z-index="1" y="18.5" text-anchor="end" style="font-size: 0.8em; font-weight: normal; fill: rgb(51, 51, 51);"></text></g></g><text x="654" class="highcharts-title" data-z-index="4" text-align="center" y="25" text-anchor="middle" transform-origin="10 22" transform="translate(0,0) scale(1 1)" style="font-size: 1.2em; font-weight: bold; fill: rgb(51, 51, 51);" aria-hidden="true"></text><text x="654" class="highcharts-subtitle" data-z-index="4" text-align="center" y="24" text-anchor="middle" transform-origin="10 15" transform="translate(0,0) scale(1 1)" style="font-size: 0.8em; fill: rgb(102, 102, 102);" aria-hidden="true"></text><text x="10" text-anchor="start" class="highcharts-caption" data-z-index="4" style="font-size: 0.8em; fill: rgb(102, 102, 102);" text-align="left" y="397" transform-origin="10 15" transform="translate(0,0) scale(1 1)" aria-hidden="true"></text><g class="highcharts-data-labels highcharts-series-0 highcharts-column-series highcharts-color-0 highcharts-tracker" data-z-index="6" opacity="1" transform="translate(65,10) scale(1 1)" aria-hidden="true"><g class="highcharts-label highcharts-data-label highcharts-data-label-color-0" data-z-index="1" filter="none" transform="translate(9,30)"><text x="13.109375" data-z-index="1" y="16" text-anchor="middle" style="font-size: 0.7em; font-weight: bold; fill: rgb(0, 0, 0);"><tspan class="highcharts-text-outline" fill="#FFFFFF" stroke="#FFFFFF" stroke-width="2px" stroke-linejoin="round" style="">30<tspan x="13.109375" dy="0">&ZeroWidthSpace;</tspan></tspan>30</text></g><g class="highcharts-label highcharts-data-label highcharts-data-label-color-0" data-z-index="1" filter="none" transform="translate(57,169)"><text x="9.125" data-z-index="1" y="16" text-anchor="middle" style="font-size: 0.7em; font-weight: bold; fill: rgb(0, 0, 0);"><tspan class="highcharts-text-outline" fill="#FFFFFF" stroke="#FFFFFF" stroke-width="2px" stroke-linejoin="round" style="">4<tspan x="9.125" dy="0">&ZeroWidthSpace;</tspan></tspan>4</text></g><g class="highcharts-label highcharts-data-label highcharts-data-label-color-0" data-z-index="1" filter="none" transform="translate(97,36)"><text x="13.109375" data-z-index="1" y="16" text-anchor="middle" style="font-size: 0.7em; font-weight: bold; fill: rgb(0, 0, 0);"><tspan class="highcharts-text-outline" fill="#FFFFFF" stroke="#FFFFFF" stroke-width="2px" stroke-linejoin="round">29<tspan x="13.109375" dy="0">&ZeroWidthSpace;</tspan></tspan>29</text></g><g class="highcharts-label highcharts-data-label highcharts-data-label-color-0" data-z-index="1" filter="none" transform="translate(141,62)"><text x="13.109375" data-z-index="1" y="16" text-anchor="middle" style="font-size: 0.7em; font-weight: bold; fill: rgb(0, 0, 0);"><tspan class="highcharts-text-outline" fill="#FFFFFF" stroke="#FFFFFF" stroke-width="2px" stroke-linejoin="round">24<tspan x="13.109375" dy="0">&ZeroWidthSpace;</tspan></tspan>24</text></g><g class="highcharts-label highcharts-data-label highcharts-data-label-color-0" data-z-index="1" filter="none" transform="translate(189,179)"><text x="9.125" data-z-index="1" y="16" text-anchor="middle" style="font-size: 0.7em; font-weight: bold; fill: rgb(0, 0, 0);"><tspan class="highcharts-text-outline" fill="#FFFFFF" stroke="#FFFFFF" stroke-width="2px" stroke-linejoin="round">2<tspan x="9.125" dy="0">&ZeroWidthSpace;</tspan></tspan>2</text></g><g class="highcharts-label highcharts-data-label highcharts-data-label-color-0" data-z-index="1" filter="none" transform="translate(229,105)"><text x="13.109375" data-z-index="1" y="16" text-anchor="middle" style="font-size: 0.7em; font-weight: bold; fill: rgb(0, 0, 0);"><tspan class="highcharts-text-outline" fill="#FFFFFF" stroke="#FFFFFF" stroke-width="2px" stroke-linejoin="round">16<tspan x="13.109375" dy="0">&ZeroWidthSpace;</tspan></tspan>16</text></g><g class="highcharts-label highcharts-data-label highcharts-data-label-color-0" data-z-index="1" filter="none" transform="translate(277,179)"><text x="9.125" data-z-index="1" y="16" text-anchor="middle" style="font-size: 0.7em; font-weight: bold; fill: rgb(0, 0, 0);"><tspan class="highcharts-text-outline" fill="#FFFFFF" stroke="#FFFFFF" stroke-width="2px" stroke-linejoin="round">2<tspan x="9.125" dy="0">&ZeroWidthSpace;</tspan></tspan>2</text></g><g class="highcharts-label highcharts-data-label highcharts-data-label-color-0" data-z-index="1" filter="none" transform="translate(317,73)"><text x="13.109375" data-z-index="1" y="16" text-anchor="middle" style="font-size: 0.7em; font-weight: bold; fill: rgb(0, 0, 0);"><tspan class="highcharts-text-outline" fill="#FFFFFF" stroke="#FFFFFF" stroke-width="2px" stroke-linejoin="round">22<tspan x="13.109375" dy="0">&ZeroWidthSpace;</tspan></tspan>22</text></g><g class="highcharts-label highcharts-data-label highcharts-data-label-color-0" data-z-index="1" filter="none" transform="translate(361,121)"><text x="13.109375" data-z-index="1" y="16" text-anchor="middle" style="font-size: 0.7em; font-weight: bold; fill: rgb(0, 0, 0);"><tspan class="highcharts-text-outline" fill="#FFFFFF" stroke="#FFFFFF" stroke-width="2px" stroke-linejoin="round">13<tspan x="13.109375" dy="0">&ZeroWidthSpace;</tspan></tspan>13</text></g><g class="highcharts-label highcharts-data-label highcharts-data-label-color-0" data-z-index="1" filter="none" transform="translate(405,110)"><text x="13.109375" data-z-index="1" y="16" text-anchor="middle" style="font-size: 0.7em; font-weight: bold; fill: rgb(0, 0, 0);"><tspan class="highcharts-text-outline" fill="#FFFFFF" stroke="#FFFFFF" stroke-width="2px" stroke-linejoin="round">15<tspan x="13.109375" dy="0">&ZeroWidthSpace;</tspan></tspan>15</text></g><g class="highcharts-label highcharts-data-label highcharts-data-label-color-0" data-z-index="1" filter="none" transform="translate(449,105)"><text x="13.109375" data-z-index="1" y="16" text-anchor="middle" style="font-size: 0.7em; font-weight: bold; fill: rgb(0, 0, 0);"><tspan class="highcharts-text-outline" fill="#FFFFFF" stroke="#FFFFFF" stroke-width="2px" stroke-linejoin="round">16<tspan x="13.109375" dy="0">&ZeroWidthSpace;</tspan></tspan>16</text></g><g class="highcharts-label highcharts-data-label highcharts-data-label-color-0" data-z-index="1" filter="none" transform="translate(497,169)"><text x="9.125" data-z-index="1" y="16" text-anchor="middle" style="font-size: 0.7em; font-weight: bold; fill: rgb(0, 0, 0);"><tspan class="highcharts-text-outline" fill="#FFFFFF" stroke="#FFFFFF" stroke-width="2px" stroke-linejoin="round">4<tspan x="9.125" dy="0">&ZeroWidthSpace;</tspan></tspan>4</text></g><g class="highcharts-label highcharts-data-label highcharts-data-label-color-0" data-z-index="1" filter="none" transform="translate(541,163)"><text x="9.125" data-z-index="1" y="16" text-anchor="middle" style="font-size: 0.7em; font-weight: bold; fill: rgb(0, 0, 0);"><tspan class="highcharts-text-outline" fill="#FFFFFF" stroke="#FFFFFF" stroke-width="2px" stroke-linejoin="round">5<tspan x="9.125" dy="0">&ZeroWidthSpace;</tspan></tspan>5</text></g><g class="highcharts-label highcharts-data-label highcharts-data-label-color-0" data-z-index="1" filter="none" transform="translate(581,73)"><text x="13.109375" data-z-index="1" y="16" text-anchor="middle" style="font-size: 0.7em; font-weight: bold; fill: rgb(0, 0, 0);"><tspan class="highcharts-text-outline" fill="#FFFFFF" stroke="#FFFFFF" stroke-width="2px" stroke-linejoin="round">22<tspan x="13.109375" dy="0">&ZeroWidthSpace;</tspan></tspan>22</text></g><g class="highcharts-label highcharts-data-label highcharts-data-label-color-0" data-z-index="1" filter="none" transform="translate(629,163)"><text x="9.125" data-z-index="1" y="16" text-anchor="middle" style="font-size: 0.7em; font-weight: bold; fill: rgb(0, 0, 0);"><tspan class="highcharts-text-outline" fill="#FFFFFF" stroke="#FFFFFF" stroke-width="2px" stroke-linejoin="round">5<tspan x="9.125" dy="0">&ZeroWidthSpace;</tspan></tspan>5</text></g><g class="highcharts-label highcharts-data-label highcharts-data-label-color-0" data-z-index="1" filter="none" transform="translate(673,158)"><text x="9.125" data-z-index="1" y="16" text-anchor="middle" style="font-size: 0.7em; font-weight: bold; fill: rgb(0, 0, 0);"><tspan class="highcharts-text-outline" fill="#FFFFFF" stroke="#FFFFFF" stroke-width="2px" stroke-linejoin="round">6<tspan x="9.125" dy="0">&ZeroWidthSpace;</tspan></tspan>6</text></g><g class="highcharts-label highcharts-data-label highcharts-data-label-color-0" data-z-index="1" filter="none" transform="translate(713,105)"><text x="13.109375" data-z-index="1" y="16" text-anchor="middle" style="font-size: 0.7em; font-weight: bold; fill: rgb(0, 0, 0);"><tspan class="highcharts-text-outline" fill="#FFFFFF" stroke="#FFFFFF" stroke-width="2px" stroke-linejoin="round">16<tspan x="13.109375" dy="0">&ZeroWidthSpace;</tspan></tspan>16</text></g><g class="highcharts-label highcharts-data-label highcharts-data-label-color-0" data-z-index="1" filter="none" transform="translate(761,179)"><text x="9.125" data-z-index="1" y="16" text-anchor="middle" style="font-size: 0.7em; font-weight: bold; fill: rgb(0, 0, 0);"><tspan class="highcharts-text-outline" fill="#FFFFFF" stroke="#FFFFFF" stroke-width="2px" stroke-linejoin="round">2<tspan x="9.125" dy="0">&ZeroWidthSpace;</tspan></tspan>2</text></g><g class="highcharts-label highcharts-data-label highcharts-data-label-color-0" data-z-index="1" filter="none" transform="translate(805,142)"><text x="9.125" data-z-index="1" y="16" text-anchor="middle" style="font-size: 0.7em; font-weight: bold; fill: rgb(0, 0, 0);"><tspan class="highcharts-text-outline" fill="#FFFFFF" stroke="#FFFFFF" stroke-width="2px" stroke-linejoin="round">9<tspan x="9.125" dy="0">&ZeroWidthSpace;</tspan></tspan>9</text></g><g class="highcharts-label highcharts-data-label highcharts-data-label-color-0" data-z-index="1" filter="none" transform="translate(845,68)"><text x="13.109375" data-z-index="1" y="16" text-anchor="middle" style="font-size: 0.7em; font-weight: bold; fill: rgb(0, 0, 0);"><tspan class="highcharts-text-outline" fill="#FFFFFF" stroke="#FFFFFF" stroke-width="2px" stroke-linejoin="round">23<tspan x="13.109375" dy="0">&ZeroWidthSpace;</tspan></tspan>23</text></g><g class="highcharts-label highcharts-data-label highcharts-data-label-color-0" data-z-index="1" filter="none" transform="translate(890,9)"><text x="13.109375" data-z-index="1" y="16" text-anchor="middle" style="font-size: 0.7em; font-weight: bold; fill: rgb(0, 0, 0);"><tspan class="highcharts-text-outline" fill="#FFFFFF" stroke="#FFFFFF" stroke-width="2px" stroke-linejoin="round">34<tspan x="13.109375" dy="0">&ZeroWidthSpace;</tspan></tspan>34</text></g><g class="highcharts-label highcharts-data-label highcharts-data-label-color-0" data-z-index="1" filter="none" transform="translate(934,105)"><text x="13.109375" data-z-index="1" y="16" text-anchor="middle" style="font-size: 0.7em; font-weight: bold; fill: rgb(0, 0, 0);"><tspan class="highcharts-text-outline" fill="#FFFFFF" stroke="#FFFFFF" stroke-width="2px" stroke-linejoin="round">16<tspan x="13.109375" dy="0">&ZeroWidthSpace;</tspan></tspan>16</text></g><g class="highcharts-label highcharts-data-label highcharts-data-label-color-0" data-z-index="1" filter="none" transform="translate(978,137)"><text x="13.109375" data-z-index="1" y="16" text-anchor="middle" style="font-size: 0.7em; font-weight: bold; fill: rgb(0, 0, 0);"><tspan class="highcharts-text-outline" fill="#FFFFFF" stroke="#FFFFFF" stroke-width="2px" stroke-linejoin="round">10<tspan x="13.109375" dy="0">&ZeroWidthSpace;</tspan></tspan>10</text></g><g class="highcharts-label highcharts-data-label highcharts-data-label-color-0" data-z-index="1" filter="none" transform="translate(1022,131)"><text x="13.109375" data-z-index="1" y="16" text-anchor="middle" style="font-size: 0.7em; font-weight: bold; fill: rgb(0, 0, 0);"><tspan class="highcharts-text-outline" fill="#FFFFFF" stroke="#FFFFFF" stroke-width="2px" stroke-linejoin="round">11<tspan x="13.109375" dy="0">&ZeroWidthSpace;</tspan></tspan>11</text></g><g class="highcharts-label highcharts-data-label highcharts-data-label-color-0" data-z-index="1" filter="none" transform="translate(1066,41)"><text x="13.109375" data-z-index="1" y="16" text-anchor="middle" style="font-size: 0.7em; font-weight: bold; fill: rgb(0, 0, 0);"><tspan class="highcharts-text-outline" fill="#FFFFFF" stroke="#FFFFFF" stroke-width="2px" stroke-linejoin="round">28<tspan x="13.109375" dy="0">&ZeroWidthSpace;</tspan></tspan>28</text></g><g class="highcharts-label highcharts-data-label highcharts-data-label-color-0" data-z-index="1" filter="none" transform="translate(1114,147)"><text x="9.125" data-z-index="1" y="16" text-anchor="middle" style="font-size: 0.7em; font-weight: bold; fill: rgb(0, 0, 0);"><tspan class="highcharts-text-outline" fill="#FFFFFF" stroke="#FFFFFF" stroke-width="2px" stroke-linejoin="round">8<tspan x="9.125" dy="0">&ZeroWidthSpace;</tspan></tspan>8</text></g><g class="highcharts-label highcharts-data-label highcharts-data-label-color-0" data-z-index="1" filter="none" transform="translate(1154,126)"><text x="13.109375" data-z-index="1" y="16" text-anchor="middle" style="font-size: 0.7em; font-weight: bold; fill: rgb(0, 0, 0);"><tspan class="highcharts-text-outline" fill="#FFFFFF" stroke="#FFFFFF" stroke-width="2px" stroke-linejoin="round">12<tspan x="13.109375" dy="0">&ZeroWidthSpace;</tspan></tspan>12</text></g><g class="highcharts-label highcharts-data-label highcharts-data-label-color-0" data-z-index="1" filter="none" transform="translate(1198,105)"><text x="13.109375" data-z-index="1" y="16" text-anchor="middle" style="font-size: 0.7em; font-weight: bold; fill: rgb(0, 0, 0);"><tspan class="highcharts-text-outline" fill="#FFFFFF" stroke="#FFFFFF" stroke-width="2px" stroke-linejoin="round">16<tspan x="13.109375" dy="0">&ZeroWidthSpace;</tspan></tspan>16</text></g></g><g class="highcharts-legend highcharts-no-tooltip" data-z-index="7" text-align="center" transform="translate(606,355)" aria-hidden="true"><rect fill="none" class="highcharts-legend-box" rx="0" ry="0" stroke="#999999" stroke-width="0" filter="none" x="0" y="0" width="95" height="30"></rect><g data-z-index="1"><g><g class="highcharts-legend-item highcharts-column-series highcharts-color-0 highcharts-series-0" data-z-index="1" transform="translate(8,3)"><text x="21" text-anchor="start" data-z-index="2" style="cursor: pointer; font-size: 0.8em; text-decoration: none; fill: rgb(51, 51, 51);" y="17">Divisions</text><rect x="2" y="6" rx="6" ry="6" width="12" height="12" fill="#28a745" class="highcharts-point" data-z-index="3"></rect></g></g></g></g><g class="highcharts-axis-labels highcharts-xaxis-labels" data-z-index="7" aria-hidden="true"><text x="89.84628426759905" text-anchor="end" transform="translate(0,0) rotate(-45 89.84628426759905 246)" style="cursor: default; font-size: 0.8em; fill: rgb(51, 51, 51);" y="246" opacity="1"><title>Directorate of Administration </title>Directorate of…</text><text x="133.88199855331905" text-anchor="end" transform="translate(0,0) rotate(-45 133.88199855331905 246)" style="cursor: default; font-size: 0.8em; fill: rgb(51, 51, 51);" y="246" opacity="1"><title>Policy and Health Diplomacy</title>Policy and Health…</text><text x="177.91771283902904" text-anchor="end" transform="translate(0,0) rotate(-45 177.91771283902904 246)" style="cursor: default; font-size: 0.8em; fill: rgb(51, 51, 51);" y="246" opacity="1"><title>Centre for Primary Healthcare</title>Centre for Primary…</text><text x="221.95342712474906" text-anchor="end" transform="translate(0,0) rotate(-45 221.95342712474906 246)" style="cursor: default; font-size: 0.8em; fill: rgb(51, 51, 51);" y="246" opacity="1">Executive Office</text><text x="265.98914141045907" text-anchor="end" transform="translate(0,0) rotate(-45 265.98914141045907 246)" style="cursor: default; font-size: 0.8em; fill: rgb(51, 51, 51);" y="246" opacity="1"><title>Office of the Director General</title>Office of the…</text><text x="310.02485569617903" text-anchor="end" transform="translate(0,0) rotate(-45 310.02485569617903 246)" style="cursor: default; font-size: 0.8em; fill: rgb(51, 51, 51);" y="246" opacity="1"><title>Public Health Institutes and Research</title>Public Health…</text><text x="354.06056998188905" text-anchor="end" transform="translate(0,0) rotate(-45 354.06056998188905 246)" style="cursor: default; font-size: 0.8em; fill: rgb(51, 51, 51);" y="246" opacity="1"><title>Office of the Deputy Director General</title>Office of the Depu…</text><text x="398.09628426759906" text-anchor="end" transform="translate(0,0) rotate(-45 398.09628426759906 246)" style="cursor: default; font-size: 0.8em; fill: rgb(51, 51, 51);" y="246" opacity="1">Southern RCC</text><text x="442.131998553319" text-anchor="end" transform="translate(0,0) rotate(-45 442.131998553319 246)" style="cursor: default; font-size: 0.8em; fill: rgb(51, 51, 51);" y="246" opacity="1"><title>Directorate of Finance</title>Directorate of…</text><text x="486.16771283902904" text-anchor="end" transform="translate(0,0) rotate(-45 486.16771283902904 246)" style="cursor: default; font-size: 0.8em; fill: rgb(51, 51, 51);" y="246" opacity="1"><title>Planning Reporting and Accountability</title>Planning Reporting…</text><text x="530.203427124749" text-anchor="end" transform="translate(0,0) rotate(-45 530.203427124749 246)" style="cursor: default; font-size: 0.8em; fill: rgb(51, 51, 51);" y="246" opacity="1"><title>Surveillance and Disease Intelligence </title>Surveillance and…</text><text x="574.2391414104591" text-anchor="end" transform="translate(0,0) rotate(-45 574.2391414104591 246)" style="cursor: default; font-size: 0.8em; fill: rgb(51, 51, 51);" y="246" opacity="1"><title>Health Economics and Financing</title>Health Economics…</text><text x="618.2748556961791" text-anchor="end" transform="translate(0,0) rotate(-45 618.2748556961791 246)" style="cursor: default; font-size: 0.8em; fill: rgb(51, 51, 51);" y="246" opacity="1"><title>Supply Chain Management</title>Supply Chain…</text><text x="662.3105699818891" text-anchor="end" transform="translate(0,0) rotate(-45 662.3105699818891 246)" style="cursor: default; font-size: 0.8em; fill: rgb(51, 51, 51);" y="246" opacity="1"><title>Emergency Preparedness and Response</title>Emergency…</text><text x="706.3462842675991" text-anchor="end" transform="translate(0,0) rotate(-45 706.3462842675991 246)" style="cursor: default; font-size: 0.8em; fill: rgb(51, 51, 51);" y="246" opacity="1"><title>Local Manufacturing of Health Commodities</title>Local Manufacturi…</text><text x="750.3819985533191" text-anchor="end" transform="translate(0,0) rotate(-45 750.3819985533191 246)" style="cursor: default; font-size: 0.8em; fill: rgb(51, 51, 51);" y="246" opacity="1"><title>Legal Affairs and Dispute Settlement</title>Legal Affairs and…</text><text x="794.4177128390292" text-anchor="end" transform="translate(0,0) rotate(-45 794.4177128390292 246)" style="cursor: default; font-size: 0.8em; fill: rgb(51, 51, 51);" y="246" opacity="1"><title>Laboratory Networks and Systems</title>Laboratory…</text><text x="838.453427124749" text-anchor="end" transform="translate(0,0) rotate(-45 838.453427124749 246)" style="cursor: default; font-size: 0.8em; fill: rgb(51, 51, 51);" y="246" opacity="1"><title>Disease Control and Prevention</title>Disease Control a…</text><text x="882.4891414104591" text-anchor="end" transform="translate(0,0) rotate(-45 882.4891414104591 246)" style="cursor: default; font-size: 0.8em; fill: rgb(51, 51, 51);" y="246" opacity="1">PIU</text><text x="926.5248556961791" text-anchor="end" transform="translate(0,0) rotate(-45 926.5248556961791 246)" style="cursor: default; font-size: 0.8em; fill: rgb(51, 51, 51);" y="246" opacity="1">Central RCC</text><text x="970.5605699818891" text-anchor="end" transform="translate(0,0) rotate(-45 970.5605699818891 246)" style="cursor: default; font-size: 0.8em; fill: rgb(51, 51, 51);" y="246" opacity="1">Eastern RCC</text><text x="1014.596284267589" text-anchor="end" transform="translate(0,0) rotate(-45 1014.596284267589 246)" style="cursor: default; font-size: 0.8em; fill: rgb(51, 51, 51);" y="246" opacity="1"><title>Directorate of Communication and Public Information</title>Directorate of…</text><text x="1058.6319985532891" text-anchor="end" transform="translate(0,0) rotate(-45 1058.6319985532891 246)" style="cursor: default; font-size: 0.8em; fill: rgb(51, 51, 51);" y="246" opacity="1"><title>Directorate of Science and Innovation</title>Directorate of…</text><text x="1102.667712838989" text-anchor="end" transform="translate(0,0) rotate(-45 1102.667712838989 246)" style="cursor: default; font-size: 0.8em; fill: rgb(51, 51, 51);" y="246" opacity="1"><title>Digital Health and Information Systems</title>Digital Health and…</text><text x="1146.7034271247892" text-anchor="end" transform="translate(0,0) rotate(-45 1146.7034271247892 246)" style="cursor: default; font-size: 0.8em; fill: rgb(51, 51, 51);" y="246" opacity="1">Western RCC</text><text x="1190.739141410489" text-anchor="end" transform="translate(0,0) rotate(-45 1190.739141410489 246)" style="cursor: default; font-size: 0.8em; fill: rgb(51, 51, 51);" y="246" opacity="1"><title>Directorate of External Relations and Strategic Engagements</title>Directorate of…</text><text x="1234.7748556961892" text-anchor="end" transform="translate(0,0) rotate(-45 1234.7748556961892 246)" style="cursor: default; font-size: 0.8em; fill: rgb(51, 51, 51);" y="246" opacity="1">Northern RCC</text><text x="1278.810569981889" text-anchor="end" transform="translate(0,0) rotate(-45 1278.810569981889 246)" style="cursor: default; font-size: 0.8em; fill: rgb(51, 51, 51);" y="246" opacity="1">IMST - External</text></g><g class="highcharts-axis-labels highcharts-yaxis-labels" data-z-index="7" aria-hidden="true"><text x="50" text-anchor="end" transform="translate(0,0)" style="cursor: default; font-size: 0.8em; fill: rgb(51, 51, 51);" y="228" opacity="1">0</text><text x="50" text-anchor="end" transform="translate(0,0)" style="cursor: default; font-size: 0.8em; fill: rgb(51, 51, 51);" y="174" opacity="1">10</text><text x="50" text-anchor="end" transform="translate(0,0)" style="cursor: default; font-size: 0.8em; fill: rgb(51, 51, 51);" y="121" opacity="1">20</text><text x="50" text-anchor="end" transform="translate(0,0)" style="cursor: default; font-size: 0.8em; fill: rgb(51, 51, 51);" y="68" opacity="1">30</text><text x="50" text-anchor="end" transform="translate(0,0)" style="cursor: default; font-size: 0.8em; fill: rgb(51, 51, 51);" y="15" opacity="1">40</text></g></svg><div aria-hidden="false" class="highcharts-a11y-proxy-container-after" style="top: 0px; left: 0px; white-space: nowrap; position: absolute;"><div class="highcharts-a11y-proxy-group highcharts-a11y-proxy-group-zoom"></div><div class="highcharts-a11y-proxy-group highcharts-a11y-proxy-group-legend" aria-label="Toggle series visibility, Chart" role="region"><ul role="list"><li style="list-style: none;"><button class="highcharts-a11y-proxy-element" tabindex="-1" aria-pressed="true" aria-label="Show Divisions" style="border-width: 0px; background-color: transparent; cursor: pointer; outline: none; opacity: 0.001; z-index: 999; overflow: hidden; padding: 0px; margin: 0px; display: block; position: absolute; width: 77.0938px; height: 15px; left: 616px; top: 363px;"></button></li></ul></div><div class="highcharts-a11y-proxy-group highcharts-a11y-proxy-group-chartMenu"><button class="highcharts-a11y-proxy-element highcharts-no-tooltip" aria-label="View chart menu, Chart" aria-expanded="false" title="Chart context menu" style="border-width: 0px; background-color: transparent; cursor: pointer; outline: none; opacity: 0.001; z-index: 999; overflow: hidden; padding: 0px; margin: 0px; display: block; position: absolute; width: 28px; height: 28px; left: 1271px; top: 6px;"></button></div></div></div><div id="highcharts-screen-reader-region-after-2" aria-hidden="false" style="position: relative;"><div aria-hidden="false" style="position: absolute; width: 1px; height: 1px; overflow: hidden; white-space: nowrap; clip: rect(1px, 1px, 1px, 1px); margin-top: -3px; opacity: 0.01;"><div id="highcharts-end-of-chart-marker-2" class="highcharts-exit-anchor" tabindex="0" aria-hidden="false">End of interactive chart.</div></div></div></div>
          </figure>
        </div>
      </div>
    </div>
  </div>
  <div class="col-12 col-lg-12 d-flex">
    <div class="card rounded-1 w-100">
      <div class="card-body">
        <div class="d-flex align-items-center">
          <div>
            <h6 class="mb-0">Staff by Member State</h6>
          </div>
        </div>
        <div>
          <figure class="highcharts-figure">
            <div id="container5" data-highcharts-chart="3" aria-hidden="false" role="region" aria-label="Chart. Highcharts interactive chart." style="overflow: hidden;"><div id="highcharts-screen-reader-region-before-3" style="position: relative;" aria-hidden="false"><div aria-hidden="false" style="position: absolute; width: 1px; height: 1px; overflow: hidden; white-space: nowrap; clip: rect(1px, 1px, 1px, 1px); margin-top: -3px; opacity: 0.01;"><h6>Chart</h6><div>Bar chart with 45 bars.</div><div><button id="hc-linkto-highcharts-data-table-3" tabindex="-1" aria-expanded="false">View as data table, Chart</button></div><div>The chart has 1 X axis displaying categories. </div><div>The chart has 1 Y axis displaying Total Staff. Data ranges from 1 to 75.</div></div></div><div aria-hidden="false" class="highcharts-announcer-container" style="position: relative;"><div aria-hidden="false" aria-live="polite" aria-atomic="true" style="position: absolute; width: 1px; height: 1px; overflow: hidden; white-space: nowrap; clip: rect(1px, 1px, 1px, 1px); margin-top: -3px; opacity: 0.01;"></div><div aria-hidden="false" aria-live="assertive" aria-atomic="true" style="position: absolute; width: 1px; height: 1px; overflow: hidden; white-space: nowrap; clip: rect(1px, 1px, 1px, 1px); margin-top: -3px; opacity: 0.01;"></div><div aria-hidden="false" aria-live="polite" aria-atomic="true" style="position: absolute; width: 1px; height: 1px; overflow: hidden; white-space: nowrap; clip: rect(1px, 1px, 1px, 1px); margin-top: -3px; opacity: 0.01;"></div><div aria-hidden="false" aria-live="polite" aria-atomic="true" style="position: absolute; width: 1px; height: 1px; overflow: hidden; white-space: nowrap; clip: rect(1px, 1px, 1px, 1px); margin-top: -3px; opacity: 0.01;"></div></div><div id="highcharts-6ffgtg1-50" dir="ltr" style="position: relative; overflow: hidden; width: 1308px; height: 400px; text-align: left; line-height: normal; z-index: 0; -webkit-tap-highlight-color: rgba(0, 0, 0, 0); user-select: none; touch-action: manipulation; outline: none; padding: 0px;" class="highcharts-container " aria-hidden="false" tabindex="0"><div aria-hidden="false" class="highcharts-a11y-proxy-container-before" style="top: 0px; left: 0px; white-space: nowrap; position: absolute;"></div><svg version="1.1" class="highcharts-root" style="font-family: -apple-system, BlinkMacSystemFont, &quot;Segoe UI&quot;, Roboto, Helvetica, Arial, &quot;Apple Color Emoji&quot;, &quot;Segoe UI Emoji&quot;, &quot;Segoe UI Symbol&quot;, sans-serif; font-size: 1rem;" xmlns="http://www.w3.org/2000/svg" width="1308" height="400" viewBox="0 0 1308 400" aria-hidden="false" aria-label="Interactive chart"><desc aria-hidden="true">Created with Highcharts 12.2.0</desc><defs aria-hidden="true"><filter id="highcharts-drop-shadow-3"><feDropShadow dx="1" dy="1" flood-color="#000000" flood-opacity="0.75" stdDeviation="2.5"></feDropShadow></filter><clipPath id="highcharts-6ffgtg1-99-"><rect x="0" y="0" width="1225" height="197" fill="none"></rect></clipPath></defs><rect fill="#ffffff" class="highcharts-background" filter="none" x="0" y="0" width="1308" height="400" rx="0" ry="0" aria-hidden="true"></rect><rect fill="none" class="highcharts-plot-background" x="73" y="10" width="1225" height="197" filter="none" aria-hidden="true"></rect><g class="highcharts-pane-group" data-z-index="0" aria-hidden="true"></g><g class="highcharts-grid highcharts-xaxis-grid" data-z-index="1" aria-hidden="true"><path fill="none" stroke="#e6e6e6" stroke-width="0" stroke-dasharray="none" data-z-index="1" class="highcharts-grid-line" d="M 100.5 10 L 100.5 207" opacity="1"></path><path fill="none" stroke="#e6e6e6" stroke-width="0" stroke-dasharray="none" data-z-index="1" class="highcharts-grid-line" d="M 127.5 10 L 127.5 207" opacity="1"></path><path fill="none" stroke="#e6e6e6" stroke-width="0" stroke-dasharray="none" data-z-index="1" class="highcharts-grid-line" d="M 154.5 10 L 154.5 207" opacity="1"></path><path fill="none" stroke="#e6e6e6" stroke-width="0" stroke-dasharray="none" data-z-index="1" class="highcharts-grid-line" d="M 181.5 10 L 181.5 207" opacity="1"></path><path fill="none" stroke="#e6e6e6" stroke-width="0" stroke-dasharray="none" data-z-index="1" class="highcharts-grid-line" d="M 209.5 10 L 209.5 207" opacity="1"></path><path fill="none" stroke="#e6e6e6" stroke-width="0" stroke-dasharray="none" data-z-index="1" class="highcharts-grid-line" d="M 236.5 10 L 236.5 207" opacity="1"></path><path fill="none" stroke="#e6e6e6" stroke-width="0" stroke-dasharray="none" data-z-index="1" class="highcharts-grid-line" d="M 263.5 10 L 263.5 207" opacity="1"></path><path fill="none" stroke="#e6e6e6" stroke-width="0" stroke-dasharray="none" data-z-index="1" class="highcharts-grid-line" d="M 290.5 10 L 290.5 207" opacity="1"></path><path fill="none" stroke="#e6e6e6" stroke-width="0" stroke-dasharray="none" data-z-index="1" class="highcharts-grid-line" d="M 318.5 10 L 318.5 207" opacity="1"></path><path fill="none" stroke="#e6e6e6" stroke-width="0" stroke-dasharray="none" data-z-index="1" class="highcharts-grid-line" d="M 345.5 10 L 345.5 207" opacity="1"></path><path fill="none" stroke="#e6e6e6" stroke-width="0" stroke-dasharray="none" data-z-index="1" class="highcharts-grid-line" d="M 372.5 10 L 372.5 207" opacity="1"></path><path fill="none" stroke="#e6e6e6" stroke-width="0" stroke-dasharray="none" data-z-index="1" class="highcharts-grid-line" d="M 399.5 10 L 399.5 207" opacity="1"></path><path fill="none" stroke="#e6e6e6" stroke-width="0" stroke-dasharray="none" data-z-index="1" class="highcharts-grid-line" d="M 426.5 10 L 426.5 207" opacity="1"></path><path fill="none" stroke="#e6e6e6" stroke-width="0" stroke-dasharray="none" data-z-index="1" class="highcharts-grid-line" d="M 454.5 10 L 454.5 207" opacity="1"></path><path fill="none" stroke="#e6e6e6" stroke-width="0" stroke-dasharray="none" data-z-index="1" class="highcharts-grid-line" d="M 481.5 10 L 481.5 207" opacity="1"></path><path fill="none" stroke="#e6e6e6" stroke-width="0" stroke-dasharray="none" data-z-index="1" class="highcharts-grid-line" d="M 508.5 10 L 508.5 207" opacity="1"></path><path fill="none" stroke="#e6e6e6" stroke-width="0" stroke-dasharray="none" data-z-index="1" class="highcharts-grid-line" d="M 535.5 10 L 535.5 207" opacity="1"></path><path fill="none" stroke="#e6e6e6" stroke-width="0" stroke-dasharray="none" data-z-index="1" class="highcharts-grid-line" d="M 563.5 10 L 563.5 207" opacity="1"></path><path fill="none" stroke="#e6e6e6" stroke-width="0" stroke-dasharray="none" data-z-index="1" class="highcharts-grid-line" d="M 590.5 10 L 590.5 207" opacity="1"></path><path fill="none" stroke="#e6e6e6" stroke-width="0" stroke-dasharray="none" data-z-index="1" class="highcharts-grid-line" d="M 617.5 10 L 617.5 207" opacity="1"></path><path fill="none" stroke="#e6e6e6" stroke-width="0" stroke-dasharray="none" data-z-index="1" class="highcharts-grid-line" d="M 644.5 10 L 644.5 207" opacity="1"></path><path fill="none" stroke="#e6e6e6" stroke-width="0" stroke-dasharray="none" data-z-index="1" class="highcharts-grid-line" d="M 671.5 10 L 671.5 207" opacity="1"></path><path fill="none" stroke="#e6e6e6" stroke-width="0" stroke-dasharray="none" data-z-index="1" class="highcharts-grid-line" d="M 699.5 10 L 699.5 207" opacity="1"></path><path fill="none" stroke="#e6e6e6" stroke-width="0" stroke-dasharray="none" data-z-index="1" class="highcharts-grid-line" d="M 726.5 10 L 726.5 207" opacity="1"></path><path fill="none" stroke="#e6e6e6" stroke-width="0" stroke-dasharray="none" data-z-index="1" class="highcharts-grid-line" d="M 753.5 10 L 753.5 207" opacity="1"></path><path fill="none" stroke="#e6e6e6" stroke-width="0" stroke-dasharray="none" data-z-index="1" class="highcharts-grid-line" d="M 780.5 10 L 780.5 207" opacity="1"></path><path fill="none" stroke="#e6e6e6" stroke-width="0" stroke-dasharray="none" data-z-index="1" class="highcharts-grid-line" d="M 808.5 10 L 808.5 207" opacity="1"></path><path fill="none" stroke="#e6e6e6" stroke-width="0" stroke-dasharray="none" data-z-index="1" class="highcharts-grid-line" d="M 835.5 10 L 835.5 207" opacity="1"></path><path fill="none" stroke="#e6e6e6" stroke-width="0" stroke-dasharray="none" data-z-index="1" class="highcharts-grid-line" d="M 862.5 10 L 862.5 207" opacity="1"></path><path fill="none" stroke="#e6e6e6" stroke-width="0" stroke-dasharray="none" data-z-index="1" class="highcharts-grid-line" d="M 889.5 10 L 889.5 207" opacity="1"></path><path fill="none" stroke="#e6e6e6" stroke-width="0" stroke-dasharray="none" data-z-index="1" class="highcharts-grid-line" d="M 916.5 10 L 916.5 207" opacity="1"></path><path fill="none" stroke="#e6e6e6" stroke-width="0" stroke-dasharray="none" data-z-index="1" class="highcharts-grid-line" d="M 944.5 10 L 944.5 207" opacity="1"></path><path fill="none" stroke="#e6e6e6" stroke-width="0" stroke-dasharray="none" data-z-index="1" class="highcharts-grid-line" d="M 971.5 10 L 971.5 207" opacity="1"></path><path fill="none" stroke="#e6e6e6" stroke-width="0" stroke-dasharray="none" data-z-index="1" class="highcharts-grid-line" d="M 998.5 10 L 998.5 207" opacity="1"></path><path fill="none" stroke="#e6e6e6" stroke-width="0" stroke-dasharray="none" data-z-index="1" class="highcharts-grid-line" d="M 1025.5 10 L 1025.5 207" opacity="1"></path><path fill="none" stroke="#e6e6e6" stroke-width="0" stroke-dasharray="none" data-z-index="1" class="highcharts-grid-line" d="M 1053.5 10 L 1053.5 207" opacity="1"></path><path fill="none" stroke="#e6e6e6" stroke-width="0" stroke-dasharray="none" data-z-index="1" class="highcharts-grid-line" d="M 1080.5 10 L 1080.5 207" opacity="1"></path><path fill="none" stroke="#e6e6e6" stroke-width="0" stroke-dasharray="none" data-z-index="1" class="highcharts-grid-line" d="M 1107.5 10 L 1107.5 207" opacity="1"></path><path fill="none" stroke="#e6e6e6" stroke-width="0" stroke-dasharray="none" data-z-index="1" class="highcharts-grid-line" d="M 1134.5 10 L 1134.5 207" opacity="1"></path><path fill="none" stroke="#e6e6e6" stroke-width="0" stroke-dasharray="none" data-z-index="1" class="highcharts-grid-line" d="M 1161.5 10 L 1161.5 207" opacity="1"></path><path fill="none" stroke="#e6e6e6" stroke-width="0" stroke-dasharray="none" data-z-index="1" class="highcharts-grid-line" d="M 1189.5 10 L 1189.5 207" opacity="1"></path><path fill="none" stroke="#e6e6e6" stroke-width="0" stroke-dasharray="none" data-z-index="1" class="highcharts-grid-line" d="M 1216.5 10 L 1216.5 207" opacity="1"></path><path fill="none" stroke="#e6e6e6" stroke-width="0" stroke-dasharray="none" data-z-index="1" class="highcharts-grid-line" d="M 1243.5 10 L 1243.5 207" opacity="1"></path><path fill="none" stroke="#e6e6e6" stroke-width="0" stroke-dasharray="none" data-z-index="1" class="highcharts-grid-line" d="M 1270.5 10 L 1270.5 207" opacity="1"></path><path fill="none" stroke="#e6e6e6" stroke-width="0" stroke-dasharray="none" data-z-index="1" class="highcharts-grid-line" d="M 1298.5 10 L 1298.5 207" opacity="1"></path><path fill="none" stroke="#e6e6e6" stroke-width="0" stroke-dasharray="none" data-z-index="1" class="highcharts-grid-line" d="M 73.5 10 L 73.5 207" opacity="1"></path></g><g class="highcharts-grid highcharts-yaxis-grid" data-z-index="1" aria-hidden="true"><path fill="none" stroke="#e6e6e6" stroke-width="1" stroke-dasharray="none" data-z-index="1" class="highcharts-grid-line" d="M 73 207.5 L 1298 207.5" opacity="1"></path><path fill="none" stroke="#e6e6e6" stroke-width="1" stroke-dasharray="none" data-z-index="1" class="highcharts-grid-line" d="M 73 157.5 L 1298 157.5" opacity="1"></path><path fill="none" stroke="#e6e6e6" stroke-width="1" stroke-dasharray="none" data-z-index="1" class="highcharts-grid-line" d="M 73 108.5 L 1298 108.5" opacity="1"></path><path fill="none" stroke="#e6e6e6" stroke-width="1" stroke-dasharray="none" data-z-index="1" class="highcharts-grid-line" d="M 73 59.5 L 1298 59.5" opacity="1"></path><path fill="none" stroke="#e6e6e6" stroke-width="1" stroke-dasharray="none" data-z-index="1" class="highcharts-grid-line" d="M 73 10.5 L 1298 10.5" opacity="1"></path></g><rect fill="none" class="highcharts-plot-border" data-z-index="1" stroke="#cccccc" stroke-width="0" x="73" y="10" width="1225" height="197" aria-hidden="true"></rect><g class="highcharts-axis highcharts-xaxis" data-z-index="2" aria-hidden="true"><path fill="none" class="highcharts-axis-line" stroke="#333333" stroke-width="1" data-z-index="7" d="M 73 207.5 L 1298 207.5"></path></g><g class="highcharts-axis highcharts-yaxis" data-z-index="2" aria-hidden="true"><text x="24.625" data-z-index="7" text-anchor="middle" transform="translate(0,0) rotate(270 24.625 108.5)" class="highcharts-axis-title" style="font-size: 0.8em; fill: rgb(102, 102, 102);" y="108.5">Total Staff</text><path fill="none" class="highcharts-axis-line" stroke="#333333" stroke-width="0" data-z-index="7" d="M 73 10 L 73 207"></path></g><g class="highcharts-series-group" data-z-index="3" filter="none" aria-hidden="false"><g class="highcharts-series highcharts-series-0 highcharts-column-series highcharts-color-0 highcharts-tracker" data-z-index="0.1" opacity="1" transform="translate(73,10) scale(1 1)" clip-path="url(#highcharts-6ffgtg1-99-)" aria-hidden="false"><path fill="#28a745" d="M 12 177 L 16 177 A 3 3 0 0 1 19 180 L 19 197 A 0 0 0 0 1 19 197 L 9 197 A 0 0 0 0 1 9 197 L 9 180 A 3 3 0 0 1 12 177 Z" stroke="#ffffff" stroke-width="0" opacity="1" filter="none" class="highcharts-point highcharts-color-0" tabindex="-1" role="img" aria-label="South Sudan, 10. Member States." style="outline: none;"></path><path fill="#28a745" d="M 39 191 L 43 191 A 3 3 0 0 1 46 194 L 46 197 A 0 0 0 0 1 46 197 L 36 197 A 0 0 0 0 1 36 197 L 36 194 A 3 3 0 0 1 39 191 Z" stroke="#ffffff" stroke-width="0" opacity="1" filter="none" class="highcharts-point highcharts-color-0" tabindex="-1" role="img" aria-label="Chad, 3. Member States." style="outline: none;"></path><path fill="#28a745" d="M 66 189 L 70 189 A 3 3 0 0 1 73 192 L 73 197 A 0 0 0 0 1 73 197 L 63 197 A 0 0 0 0 1 63 197 L 63 192 A 3 3 0 0 1 66 189 Z" stroke="#ffffff" stroke-width="0" opacity="1" filter="none" class="highcharts-point highcharts-color-0" tabindex="-1" role="img" aria-label="Japan, 4. Member States." style="outline: none;"></path><path fill="#28a745" d="M 93 140 L 97 140 A 3 3 0 0 1 100 143 L 100 197 A 0 0 0 0 1 100 197 L 90 197 A 0 0 0 0 1 90 197 L 90 143 A 3 3 0 0 1 93 140 Z" stroke="#ffffff" stroke-width="0" opacity="1" filter="none" class="highcharts-point highcharts-color-0" tabindex="-1" role="img" aria-label="DR Congo, 29. Member States." style="outline: none;"></path><path fill="#28a745" d="M 121 49 L 125 49 A 3 3 0 0 1 128 52 L 128 197 A 0 0 0 0 1 128 197 L 118 197 A 0 0 0 0 1 118 197 L 118 52 A 3 3 0 0 1 121 49 Z" stroke="#ffffff" stroke-width="0" opacity="1" filter="none" class="highcharts-point highcharts-color-0" tabindex="-1" role="img" aria-label="Ethiopia, 75. Member States." style="outline: none;"></path><path fill="#28a745" d="M 148 97 L 152 97 A 3 3 0 0 1 155 100 L 155 197 A 0 0 0 0 1 155 197 L 145 197 A 0 0 0 0 1 145 197 L 145 100 A 3 3 0 0 1 148 97 Z" stroke="#ffffff" stroke-width="0" opacity="1" filter="none" class="highcharts-point highcharts-color-0" tabindex="-1" role="img" aria-label="Nigeria, 51. Member States." style="outline: none;"></path><path fill="#28a745" d="M 175 179 L 179 179 A 3 3 0 0 1 182 182 L 182 197 A 0 0 0 0 1 182 197 L 172 197 A 0 0 0 0 1 172 197 L 172 182 A 3 3 0 0 1 175 179 Z" stroke="#ffffff" stroke-width="0" opacity="1" filter="none" class="highcharts-point highcharts-color-0" tabindex="-1" role="img" aria-label="Tanzania, 9. Member States." style="outline: none;"></path><path fill="#28a745" d="M 202 116 L 206 116 A 3 3 0 0 1 209 119 L 209 197 A 0 0 0 0 1 209 197 L 199 197 A 0 0 0 0 1 199 197 L 199 119 A 3 3 0 0 1 202 116 Z" stroke="#ffffff" stroke-width="0" opacity="1" filter="none" class="highcharts-point highcharts-color-0" tabindex="-1" role="img" aria-label="Kenya, 41. Member States." style="outline: none;"></path><path fill="#28a745" d="M 229 195 L 233 195 A 3 3 0 0 1 235.82842712474618 197 L 235.82842712474618 197 A 0 0 0 0 1 235.82842712474618 197 L 235.82842712474618 197 A 0 0 0 0 1 226.17157287525382 197 L 226.17157287525382 197 A 3 3 0 0 1 229 195 Z" stroke="#ffffff" stroke-width="0" opacity="1" filter="none" class="highcharts-point highcharts-color-0" tabindex="-1" role="img" aria-label="Morocco, 1. Member States." style="outline: none;"></path><path fill="#28a745" d="M 257 175 L 261 175 A 3 3 0 0 1 264 178 L 264 197 A 0 0 0 0 1 264 197 L 254 197 A 0 0 0 0 1 254 197 L 254 178 A 3 3 0 0 1 257 175 Z" stroke="#ffffff" stroke-width="0" opacity="1" filter="none" class="highcharts-point highcharts-color-0" tabindex="-1" role="img" aria-label="Zambia, 11. Member States." style="outline: none;"></path><path fill="#28a745" d="M 284 187 L 288 187 A 3 3 0 0 1 291 190 L 291 197 A 0 0 0 0 1 291 197 L 281 197 A 0 0 0 0 1 281 197 L 281 190 A 3 3 0 0 1 284 187 Z" stroke="#ffffff" stroke-width="0" opacity="1" filter="none" class="highcharts-point highcharts-color-0" tabindex="-1" role="img" aria-label="USA, 5. Member States." style="outline: none;"></path><path fill="#28a745" d="M 311 193 L 315 193 A 3 3 0 0 1 318 196 L 318 197 A 0 0 0 0 1 318 197 L 308 197 A 0 0 0 0 1 308 197 L 308 196 A 3 3 0 0 1 311 193 Z" stroke="#ffffff" stroke-width="0" opacity="1" filter="none" class="highcharts-point highcharts-color-0" tabindex="-1" role="img" aria-label="Cote d`Ivoire, 2. Member States." style="outline: none;"></path><path fill="#28a745" d="M 338 195 L 342 195 A 3 3 0 0 1 344.8284271247462 197 L 344.8284271247462 197 A 0 0 0 0 1 344.8284271247462 197 L 344.8284271247462 197 A 0 0 0 0 1 335.1715728752538 197 L 335.1715728752538 197 A 3 3 0 0 1 338 195 Z" stroke="#ffffff" stroke-width="0" opacity="1" filter="none" class="highcharts-point highcharts-color-0" tabindex="-1" role="img" aria-label="Guinea-Bissau, 1. Member States." style="outline: none;"></path><path fill="#28a745" d="M 366 156 L 370 156 A 3 3 0 0 1 373 159 L 373 197 A 0 0 0 0 1 373 197 L 363 197 A 0 0 0 0 1 363 197 L 363 159 A 3 3 0 0 1 366 156 Z" stroke="#ffffff" stroke-width="0" opacity="1" filter="none" class="highcharts-point highcharts-color-0" tabindex="-1" role="img" aria-label="Cameroon, 21. Member States." style="outline: none;"></path><path fill="#28a745" d="M 393 164 L 397 164 A 3 3 0 0 1 400 167 L 400 197 A 0 0 0 0 1 400 197 L 390 197 A 0 0 0 0 1 390 197 L 390 167 A 3 3 0 0 1 393 164 Z" stroke="#ffffff" stroke-width="0" opacity="1" filter="none" class="highcharts-point highcharts-color-0" tabindex="-1" role="img" aria-label="Zimbabwe, 17. Member States." style="outline: none;"></path><path fill="#28a745" d="M 420 183 L 424 183 A 3 3 0 0 1 427 186 L 427 197 A 0 0 0 0 1 427 197 L 417 197 A 0 0 0 0 1 417 197 L 417 186 A 3 3 0 0 1 420 183 Z" stroke="#ffffff" stroke-width="0" opacity="1" filter="none" class="highcharts-point highcharts-color-0" tabindex="-1" role="img" aria-label="Rwanda, 7. Member States." style="outline: none;"></path><path fill="#28a745" d="M 447 187 L 451 187 A 3 3 0 0 1 454 190 L 454 197 A 0 0 0 0 1 454 197 L 444 197 A 0 0 0 0 1 444 197 L 444 190 A 3 3 0 0 1 447 187 Z" stroke="#ffffff" stroke-width="0" opacity="1" filter="none" class="highcharts-point highcharts-color-0" tabindex="-1" role="img" aria-label="Egypt, 5. Member States." style="outline: none;"></path><path fill="#28a745" d="M 474 156 L 478 156 A 3 3 0 0 1 481 159 L 481 197 A 0 0 0 0 1 481 197 L 471 197 A 0 0 0 0 1 471 197 L 471 159 A 3 3 0 0 1 474 156 Z" stroke="#ffffff" stroke-width="0" opacity="1" filter="none" class="highcharts-point highcharts-color-0" tabindex="-1" role="img" aria-label="Uganda, 21. Member States." style="outline: none;"></path><path fill="#28a745" d="M 502 187 L 506 187 A 3 3 0 0 1 509 190 L 509 197 A 0 0 0 0 1 509 197 L 499 197 A 0 0 0 0 1 499 197 L 499 190 A 3 3 0 0 1 502 187 Z" stroke="#ffffff" stroke-width="0" opacity="1" filter="none" class="highcharts-point highcharts-color-0" tabindex="-1" role="img" aria-label="South Africa, 5. Member States." style="outline: none;"></path><path fill="#28a745" d="M 529 187 L 533 187 A 3 3 0 0 1 536 190 L 536 197 A 0 0 0 0 1 536 197 L 526 197 A 0 0 0 0 1 526 197 L 526 190 A 3 3 0 0 1 529 187 Z" stroke="#ffffff" stroke-width="0" opacity="1" filter="none" class="highcharts-point highcharts-color-0" tabindex="-1" role="img" aria-label="Burkina Faso, 5. Member States." style="outline: none;"></path><path fill="#28a745" d="M 556 193 L 560 193 A 3 3 0 0 1 563 196 L 563 197 A 0 0 0 0 1 563 197 L 553 197 A 0 0 0 0 1 553 197 L 553 196 A 3 3 0 0 1 556 193 Z" stroke="#ffffff" stroke-width="0" opacity="1" filter="none" class="highcharts-point highcharts-color-0" tabindex="-1" role="img" aria-label="Togo, 2. Member States." style="outline: none;"></path><path fill="#28a745" d="M 583 181 L 587 181 A 3 3 0 0 1 590 184 L 590 197 A 0 0 0 0 1 590 197 L 580 197 A 0 0 0 0 1 580 197 L 580 184 A 3 3 0 0 1 583 181 Z" stroke="#ffffff" stroke-width="0" opacity="1" filter="none" class="highcharts-point highcharts-color-0" tabindex="-1" role="img" aria-label="Ghana, 8. Member States." style="outline: none;"></path><path fill="#28a745" d="M 611 169 L 615 169 A 3 3 0 0 1 618 172 L 618 197 A 0 0 0 0 1 618 197 L 608 197 A 0 0 0 0 1 608 197 L 608 172 A 3 3 0 0 1 611 169 Z" stroke="#ffffff" stroke-width="0" opacity="1" filter="none" class="highcharts-point highcharts-color-0" tabindex="-1" role="img" aria-label="Burundi, 14. Member States." style="outline: none;"></path><path fill="#28a745" d="M 638 195 L 642 195 A 3 3 0 0 1 644.8284271247462 197 L 644.8284271247462 197 A 0 0 0 0 1 644.8284271247462 197 L 644.8284271247462 197 A 0 0 0 0 1 635.1715728752538 197 L 635.1715728752538 197 A 3 3 0 0 1 638 195 Z" stroke="#ffffff" stroke-width="0" opacity="1" filter="none" class="highcharts-point highcharts-color-0" tabindex="-1" role="img" aria-label="Sierra Leone, 1. Member States." style="outline: none;"></path><path fill="#28a745" d="M 665 193 L 669 193 A 3 3 0 0 1 672 196 L 672 197 A 0 0 0 0 1 672 197 L 662 197 A 0 0 0 0 1 662 197 L 662 196 A 3 3 0 0 1 665 193 Z" stroke="#ffffff" stroke-width="0" opacity="1" filter="none" class="highcharts-point highcharts-color-0" tabindex="-1" role="img" aria-label="Benin, 2. Member States." style="outline: none;"></path><path fill="#28a745" d="M 692 189 L 696 189 A 3 3 0 0 1 699 192 L 699 197 A 0 0 0 0 1 699 197 L 689 197 A 0 0 0 0 1 689 197 L 689 192 A 3 3 0 0 1 692 189 Z" stroke="#ffffff" stroke-width="0" opacity="1" filter="none" class="highcharts-point highcharts-color-0" tabindex="-1" role="img" aria-label="Mali, 4. Member States." style="outline: none;"></path><path fill="#28a745" d="M 719 185 L 723 185 A 3 3 0 0 1 726 188 L 726 197 A 0 0 0 0 1 726 197 L 716 197 A 0 0 0 0 1 716 197 L 716 188 A 3 3 0 0 1 719 185 Z" stroke="#ffffff" stroke-width="0" opacity="1" filter="none" class="highcharts-point highcharts-color-0" tabindex="-1" role="img" aria-label="Malawi, 6. Member States." style="outline: none;"></path><path fill="#28a745" d="M 747 191 L 751 191 A 3 3 0 0 1 754 194 L 754 197 A 0 0 0 0 1 754 197 L 744 197 A 0 0 0 0 1 744 197 L 744 194 A 3 3 0 0 1 747 191 Z" stroke="#ffffff" stroke-width="0" opacity="1" filter="none" class="highcharts-point highcharts-color-0" tabindex="-1" role="img" aria-label="Congo Republic, 3. Member States." style="outline: none;"></path><path fill="#28a745" d="M 774 187 L 778 187 A 3 3 0 0 1 781 190 L 781 197 A 0 0 0 0 1 781 197 L 771 197 A 0 0 0 0 1 771 197 L 771 190 A 3 3 0 0 1 774 187 Z" stroke="#ffffff" stroke-width="0" opacity="1" filter="none" class="highcharts-point highcharts-color-0" tabindex="-1" role="img" aria-label="Gabon, 5. Member States." style="outline: none;"></path><path fill="#28a745" d="M 801 187 L 805 187 A 3 3 0 0 1 808 190 L 808 197 A 0 0 0 0 1 808 197 L 798 197 A 0 0 0 0 1 798 197 L 798 190 A 3 3 0 0 1 801 187 Z" stroke="#ffffff" stroke-width="0" opacity="1" filter="none" class="highcharts-point highcharts-color-0" tabindex="-1" role="img" aria-label="Gambia, 5. Member States." style="outline: none;"></path><path fill="#28a745" d="M 828 189 L 832 189 A 3 3 0 0 1 835 192 L 835 197 A 0 0 0 0 1 835 197 L 825 197 A 0 0 0 0 1 825 197 L 825 192 A 3 3 0 0 1 828 189 Z" stroke="#ffffff" stroke-width="0" opacity="1" filter="none" class="highcharts-point highcharts-color-0" tabindex="-1" role="img" aria-label="Liberia, 4. Member States." style="outline: none;"></path><path fill="#28a745" d="M 856 195 L 860 195 A 3 3 0 0 1 862.8284271247462 197 L 862.8284271247462 197 A 0 0 0 0 1 862.8284271247462 197 L 862.8284271247462 197 A 0 0 0 0 1 853.1715728752538 197 L 853.1715728752538 197 A 3 3 0 0 1 856 195 Z" stroke="#ffffff" stroke-width="0" opacity="1" filter="none" class="highcharts-point highcharts-color-0" tabindex="-1" role="img" aria-label="Central African Republic, 1. Member States." style="outline: none;"></path><path fill="#28a745" d="M 883 193 L 887 193 A 3 3 0 0 1 890 196 L 890 197 A 0 0 0 0 1 890 197 L 880 197 A 0 0 0 0 1 880 197 L 880 196 A 3 3 0 0 1 883 193 Z" stroke="#ffffff" stroke-width="0" opacity="1" filter="none" class="highcharts-point highcharts-color-0" tabindex="-1" role="img" aria-label="Djibouti, 2. Member States." style="outline: none;"></path><path fill="#28a745" d="M 910 189 L 914 189 A 3 3 0 0 1 917 192 L 917 197 A 0 0 0 0 1 917 197 L 907 197 A 0 0 0 0 1 907 197 L 907 192 A 3 3 0 0 1 910 189 Z" stroke="#ffffff" stroke-width="0" opacity="1" filter="none" class="highcharts-point highcharts-color-0" tabindex="-1" role="img" aria-label="Namibia, 4. Member States." style="outline: none;"></path><path fill="#28a745" d="M 937 191 L 941 191 A 3 3 0 0 1 944 194 L 944 197 A 0 0 0 0 1 944 197 L 934 197 A 0 0 0 0 1 934 197 L 934 194 A 3 3 0 0 1 937 191 Z" stroke="#ffffff" stroke-width="0" opacity="1" filter="none" class="highcharts-point highcharts-color-0" tabindex="-1" role="img" aria-label="Algeria, 3. Member States." style="outline: none;"></path><path fill="#28a745" d="M 964 193 L 968 193 A 3 3 0 0 1 971 196 L 971 197 A 0 0 0 0 1 971 197 L 961 197 A 0 0 0 0 1 961 197 L 961 196 A 3 3 0 0 1 964 193 Z" stroke="#ffffff" stroke-width="0" opacity="1" filter="none" class="highcharts-point highcharts-color-0" tabindex="-1" role="img" aria-label="Guinea, 2. Member States." style="outline: none;"></path><path fill="#28a745" d="M 992 191 L 996 191 A 3 3 0 0 1 999 194 L 999 197 A 0 0 0 0 1 999 197 L 989 197 A 0 0 0 0 1 989 197 L 989 194 A 3 3 0 0 1 992 191 Z" stroke="#ffffff" stroke-width="0" opacity="1" filter="none" class="highcharts-point highcharts-color-0" tabindex="-1" role="img" aria-label="Eswatini, 3. Member States." style="outline: none;"></path><path fill="#28a745" d="M 1019 195 L 1023 195 A 3 3 0 0 1 1025.8284271247462 197 L 1025.8284271247462 197 A 0 0 0 0 1 1025.8284271247462 197 L 1025.8284271247462 197 A 0 0 0 0 1 1016.1715728752538 197 L 1016.1715728752538 197 A 3 3 0 0 1 1019 195 Z" stroke="#ffffff" stroke-width="0" opacity="1" filter="none" class="highcharts-point highcharts-color-0" tabindex="-1" role="img" aria-label="Botswana, 1. Member States." style="outline: none;"></path><path fill="#28a745" d="M 1046 195 L 1050 195 A 3 3 0 0 1 1052.8284271247462 197 L 1052.8284271247462 197 A 0 0 0 0 1 1052.8284271247462 197 L 1052.8284271247462 197 A 0 0 0 0 1 1043.1715728752538 197 L 1043.1715728752538 197 A 3 3 0 0 1 1046 195 Z" stroke="#ffffff" stroke-width="0" opacity="1" filter="none" class="highcharts-point highcharts-color-0" tabindex="-1" role="img" aria-label="Angola, 1. Member States." style="outline: none;"></path><path fill="#28a745" d="M 1073 195 L 1077 195 A 3 3 0 0 1 1079.8284271247462 197 L 1079.8284271247462 197 A 0 0 0 0 1 1079.8284271247462 197 L 1079.8284271247462 197 A 0 0 0 0 1 1070.1715728752538 197 L 1070.1715728752538 197 A 3 3 0 0 1 1073 195 Z" stroke="#ffffff" stroke-width="0" opacity="1" filter="none" class="highcharts-point highcharts-color-0" tabindex="-1" role="img" aria-label="Sudan, 1. Member States." style="outline: none;"></path><path fill="#28a745" d="M 1101 189 L 1105 189 A 3 3 0 0 1 1108 192 L 1108 197 A 0 0 0 0 1 1108 197 L 1098 197 A 0 0 0 0 1 1098 197 L 1098 192 A 3 3 0 0 1 1101 189 Z" stroke="#ffffff" stroke-width="0" opacity="1" filter="none" class="highcharts-point highcharts-color-0" tabindex="-1" role="img" aria-label="Senegal, 4. Member States." style="outline: none;"></path><path fill="#28a745" d="M 1128 195 L 1132 195 A 3 3 0 0 1 1134.8284271247462 197 L 1134.8284271247462 197 A 0 0 0 0 1 1134.8284271247462 197 L 1134.8284271247462 197 A 0 0 0 0 1 1125.1715728752538 197 L 1125.1715728752538 197 A 3 3 0 0 1 1128 195 Z" stroke="#ffffff" stroke-width="0" opacity="1" filter="none" class="highcharts-point highcharts-color-0" tabindex="-1" role="img" aria-label="Tunisia, 1. Member States." style="outline: none;"></path><path fill="#28a745" d="M 1155 195 L 1159 195 A 3 3 0 0 1 1161.8284271247462 197 L 1161.8284271247462 197 A 0 0 0 0 1 1161.8284271247462 197 L 1161.8284271247462 197 A 0 0 0 0 1 1152.1715728752538 197 L 1152.1715728752538 197 A 3 3 0 0 1 1155 195 Z" stroke="#ffffff" stroke-width="0" opacity="1" filter="none" class="highcharts-point highcharts-color-0" tabindex="-1" role="img" aria-label="Libya, 1. Member States." style="outline: none;"></path><path fill="#28a745" d="M 1182 195 L 1186 195 A 3 3 0 0 1 1188.8284271247462 197 L 1188.8284271247462 197 A 0 0 0 0 1 1188.8284271247462 197 L 1188.8284271247462 197 A 0 0 0 0 1 1179.1715728752538 197 L 1179.1715728752538 197 A 3 3 0 0 1 1182 195 Z" stroke="#ffffff" stroke-width="0" opacity="1" filter="none" class="highcharts-point highcharts-color-0" tabindex="-1" role="img" aria-label="Somalia, 1. Member States." style="outline: none;"></path><path fill="#28a745" d="M 1209 195 L 1213 195 A 3 3 0 0 1 1215.8284271247462 197 L 1215.8284271247462 197 A 0 0 0 0 1 1215.8284271247462 197 L 1215.8284271247462 197 A 0 0 0 0 1 1206.1715728752538 197 L 1206.1715728752538 197 A 3 3 0 0 1 1209 195 Z" stroke="#ffffff" stroke-width="0" opacity="1" filter="none" class="highcharts-point highcharts-color-0" tabindex="-1" role="img" aria-label="Lesotho, 1. Member States." style="outline: none;"></path></g><g class="highcharts-markers highcharts-series-0 highcharts-column-series highcharts-color-0" data-z-index="0.1" opacity="1" transform="translate(73,10) scale(1 1)" clip-path="none" aria-hidden="true"></g></g><g class="highcharts-exporting-group" data-z-index="3" aria-hidden="true"><g class="highcharts-no-tooltip highcharts-button highcharts-contextbutton" stroke-linecap="round" style="cursor: pointer;" transform="translate(1270,5)"><title>Chart context menu</title><rect fill="#ffffff" class="highcharts-button-box" x="0.5" y="0.5" width="28" height="28" rx="2" ry="2" stroke="none" stroke-width="1"></rect><path fill="#666666" d="M 8 9.5 L 22 9.5 M 8 14.5 L 22 14.5 M 8 19.5 L 22 19.5" class="highcharts-button-symbol" data-z-index="1" stroke="#666666" stroke-width="3"></path><text x="28" data-z-index="1" y="18.5" text-anchor="end" style="font-size: 0.8em; font-weight: normal; fill: rgb(51, 51, 51);"></text></g></g><text x="654" class="highcharts-title" data-z-index="4" text-align="center" y="25" text-anchor="middle" transform-origin="10 22" transform="translate(0,0) scale(1 1)" style="font-size: 1.2em; font-weight: bold; fill: rgb(51, 51, 51);" aria-hidden="true"></text><text x="654" class="highcharts-subtitle" data-z-index="4" text-align="center" y="24" text-anchor="middle" transform-origin="10 15" transform="translate(0,0) scale(1 1)" style="font-size: 0.8em; fill: rgb(102, 102, 102);" aria-hidden="true"></text><text x="10" text-anchor="start" class="highcharts-caption" data-z-index="4" style="font-size: 0.8em; fill: rgb(102, 102, 102);" text-align="left" y="397" transform-origin="10 15" transform="translate(0,0) scale(1 1)" aria-hidden="true"></text><g class="highcharts-data-labels highcharts-series-0 highcharts-column-series highcharts-color-0 highcharts-tracker" data-z-index="6" opacity="1" transform="translate(73,10) scale(1 1)" aria-hidden="true"><g class="highcharts-label highcharts-data-label highcharts-data-label-color-0" data-z-index="1" filter="none" transform="translate(2,154)"><text x="12.25390625" data-z-index="1" y="16" text-anchor="middle" style="font-size: 0.7em; font-weight: bold; fill: rgb(0, 0, 0);"><tspan class="highcharts-text-outline" fill="#FFFFFF" stroke="#FFFFFF" stroke-width="2px" stroke-linejoin="round" style="">10<tspan x="12.25390625" dy="0">&ZeroWidthSpace;</tspan></tspan>10</text></g><g class="highcharts-label highcharts-data-label highcharts-data-label-color-0" data-z-index="1" filter="none" transform="translate(32,168)"><text x="9.0234375" data-z-index="1" y="16" text-anchor="middle" style="font-size: 0.7em; font-weight: bold; fill: rgb(0, 0, 0);"><tspan class="highcharts-text-outline" fill="#FFFFFF" stroke="#FFFFFF" stroke-width="2px" stroke-linejoin="round" style="">3<tspan x="9.0234375" dy="0">&ZeroWidthSpace;</tspan></tspan>3</text></g><g class="highcharts-label highcharts-data-label highcharts-data-label-color-0" data-z-index="1" filter="none" transform="translate(59,166)"><text x="9.0234375" data-z-index="1" y="16" text-anchor="middle" style="font-size: 0.7em; font-weight: bold; fill: rgb(0, 0, 0);"><tspan class="highcharts-text-outline" fill="#FFFFFF" stroke="#FFFFFF" stroke-width="2px" stroke-linejoin="round">4<tspan x="9.0234375" dy="0">&ZeroWidthSpace;</tspan></tspan>4</text></g><g class="highcharts-label highcharts-data-label highcharts-data-label-color-0" data-z-index="1" filter="none" transform="translate(83,117)"><text x="12.25390625" data-z-index="1" y="16" text-anchor="middle" style="font-size: 0.7em; font-weight: bold; fill: rgb(0, 0, 0);"><tspan class="highcharts-text-outline" fill="#FFFFFF" stroke="#FFFFFF" stroke-width="2px" stroke-linejoin="round">29<tspan x="12.25390625" dy="0">&ZeroWidthSpace;</tspan></tspan>29</text></g><g class="highcharts-label highcharts-data-label highcharts-data-label-color-0" data-z-index="1" filter="none" transform="translate(111,26)"><text x="12.25390625" data-z-index="1" y="16" text-anchor="middle" style="font-size: 0.7em; font-weight: bold; fill: rgb(0, 0, 0);"><tspan class="highcharts-text-outline" fill="#FFFFFF" stroke="#FFFFFF" stroke-width="2px" stroke-linejoin="round">75<tspan x="12.25390625" dy="0">&ZeroWidthSpace;</tspan></tspan>75</text></g><g class="highcharts-label highcharts-data-label highcharts-data-label-color-0" data-z-index="1" filter="none" transform="translate(138,74)"><text x="12.25390625" data-z-index="1" y="16" text-anchor="middle" style="font-size: 0.7em; font-weight: bold; fill: rgb(0, 0, 0);"><tspan class="highcharts-text-outline" fill="#FFFFFF" stroke="#FFFFFF" stroke-width="2px" stroke-linejoin="round">51<tspan x="12.25390625" dy="0">&ZeroWidthSpace;</tspan></tspan>51</text></g><g class="highcharts-label highcharts-data-label highcharts-data-label-color-0" data-z-index="1" filter="none" transform="translate(168,156)"><text x="9.0234375" data-z-index="1" y="16" text-anchor="middle" style="font-size: 0.7em; font-weight: bold; fill: rgb(0, 0, 0);"><tspan class="highcharts-text-outline" fill="#FFFFFF" stroke="#FFFFFF" stroke-width="2px" stroke-linejoin="round">9<tspan x="9.0234375" dy="0">&ZeroWidthSpace;</tspan></tspan>9</text></g><g class="highcharts-label highcharts-data-label highcharts-data-label-color-0" data-z-index="1" filter="none" transform="translate(192,93)"><text x="12.25390625" data-z-index="1" y="16" text-anchor="middle" style="font-size: 0.7em; font-weight: bold; fill: rgb(0, 0, 0);"><tspan class="highcharts-text-outline" fill="#FFFFFF" stroke="#FFFFFF" stroke-width="2px" stroke-linejoin="round">41<tspan x="12.25390625" dy="0">&ZeroWidthSpace;</tspan></tspan>41</text></g><g class="highcharts-label highcharts-data-label highcharts-data-label-color-0" data-z-index="1" filter="none" transform="translate(222,172)"><text x="9.0234375" data-z-index="1" y="16" text-anchor="middle" style="font-size: 0.7em; font-weight: bold; fill: rgb(0, 0, 0);"><tspan class="highcharts-text-outline" fill="#FFFFFF" stroke="#FFFFFF" stroke-width="2px" stroke-linejoin="round">1<tspan x="9.0234375" dy="0">&ZeroWidthSpace;</tspan></tspan>1</text></g><g class="highcharts-label highcharts-data-label highcharts-data-label-color-0" data-z-index="1" filter="none" transform="translate(247,152)"><text x="12.25390625" data-z-index="1" y="16" text-anchor="middle" style="font-size: 0.7em; font-weight: bold; fill: rgb(0, 0, 0);"><tspan class="highcharts-text-outline" fill="#FFFFFF" stroke="#FFFFFF" stroke-width="2px" stroke-linejoin="round">11<tspan x="12.25390625" dy="0">&ZeroWidthSpace;</tspan></tspan>11</text></g><g class="highcharts-label highcharts-data-label highcharts-data-label-color-0" data-z-index="1" filter="none" transform="translate(277,164)"><text x="9.0234375" data-z-index="1" y="16" text-anchor="middle" style="font-size: 0.7em; font-weight: bold; fill: rgb(0, 0, 0);"><tspan class="highcharts-text-outline" fill="#FFFFFF" stroke="#FFFFFF" stroke-width="2px" stroke-linejoin="round">5<tspan x="9.0234375" dy="0">&ZeroWidthSpace;</tspan></tspan>5</text></g><g class="highcharts-label highcharts-data-label highcharts-data-label-color-0" data-z-index="1" filter="none" transform="translate(304,170)"><text x="9.0234375" data-z-index="1" y="16" text-anchor="middle" style="font-size: 0.7em; font-weight: bold; fill: rgb(0, 0, 0);"><tspan class="highcharts-text-outline" fill="#FFFFFF" stroke="#FFFFFF" stroke-width="2px" stroke-linejoin="round">2<tspan x="9.0234375" dy="0">&ZeroWidthSpace;</tspan></tspan>2</text></g><g class="highcharts-label highcharts-data-label highcharts-data-label-color-0" data-z-index="1" filter="none" transform="translate(331,172)"><text x="9.0234375" data-z-index="1" y="16" text-anchor="middle" style="font-size: 0.7em; font-weight: bold; fill: rgb(0, 0, 0);"><tspan class="highcharts-text-outline" fill="#FFFFFF" stroke="#FFFFFF" stroke-width="2px" stroke-linejoin="round">1<tspan x="9.0234375" dy="0">&ZeroWidthSpace;</tspan></tspan>1</text></g><g class="highcharts-label highcharts-data-label highcharts-data-label-color-0" data-z-index="1" filter="none" transform="translate(356,133)"><text x="12.25390625" data-z-index="1" y="16" text-anchor="middle" style="font-size: 0.7em; font-weight: bold; fill: rgb(0, 0, 0);"><tspan class="highcharts-text-outline" fill="#FFFFFF" stroke="#FFFFFF" stroke-width="2px" stroke-linejoin="round">21<tspan x="12.25390625" dy="0">&ZeroWidthSpace;</tspan></tspan>21</text></g><g class="highcharts-label highcharts-data-label highcharts-data-label-color-0" data-z-index="1" filter="none" transform="translate(383,141)"><text x="12.25390625" data-z-index="1" y="16" text-anchor="middle" style="font-size: 0.7em; font-weight: bold; fill: rgb(0, 0, 0);"><tspan class="highcharts-text-outline" fill="#FFFFFF" stroke="#FFFFFF" stroke-width="2px" stroke-linejoin="round">17<tspan x="12.25390625" dy="0">&ZeroWidthSpace;</tspan></tspan>17</text></g><g class="highcharts-label highcharts-data-label highcharts-data-label-color-0" data-z-index="1" filter="none" transform="translate(413,160)"><text x="9.0234375" data-z-index="1" y="16" text-anchor="middle" style="font-size: 0.7em; font-weight: bold; fill: rgb(0, 0, 0);"><tspan class="highcharts-text-outline" fill="#FFFFFF" stroke="#FFFFFF" stroke-width="2px" stroke-linejoin="round">7<tspan x="9.0234375" dy="0">&ZeroWidthSpace;</tspan></tspan>7</text></g><g class="highcharts-label highcharts-data-label highcharts-data-label-color-0" data-z-index="1" filter="none" transform="translate(440,164)"><text x="9.0234375" data-z-index="1" y="16" text-anchor="middle" style="font-size: 0.7em; font-weight: bold; fill: rgb(0, 0, 0);"><tspan class="highcharts-text-outline" fill="#FFFFFF" stroke="#FFFFFF" stroke-width="2px" stroke-linejoin="round">5<tspan x="9.0234375" dy="0">&ZeroWidthSpace;</tspan></tspan>5</text></g><g class="highcharts-label highcharts-data-label highcharts-data-label-color-0" data-z-index="1" filter="none" transform="translate(464,133)"><text x="12.25390625" data-z-index="1" y="16" text-anchor="middle" style="font-size: 0.7em; font-weight: bold; fill: rgb(0, 0, 0);"><tspan class="highcharts-text-outline" fill="#FFFFFF" stroke="#FFFFFF" stroke-width="2px" stroke-linejoin="round">21<tspan x="12.25390625" dy="0">&ZeroWidthSpace;</tspan></tspan>21</text></g><g class="highcharts-label highcharts-data-label highcharts-data-label-color-0" data-z-index="1" filter="none" transform="translate(495,164)"><text x="9.0234375" data-z-index="1" y="16" text-anchor="middle" style="font-size: 0.7em; font-weight: bold; fill: rgb(0, 0, 0);"><tspan class="highcharts-text-outline" fill="#FFFFFF" stroke="#FFFFFF" stroke-width="2px" stroke-linejoin="round">5<tspan x="9.0234375" dy="0">&ZeroWidthSpace;</tspan></tspan>5</text></g><g class="highcharts-label highcharts-data-label highcharts-data-label-color-0" data-z-index="1" filter="none" transform="translate(522,164)"><text x="9.0234375" data-z-index="1" y="16" text-anchor="middle" style="font-size: 0.7em; font-weight: bold; fill: rgb(0, 0, 0);"><tspan class="highcharts-text-outline" fill="#FFFFFF" stroke="#FFFFFF" stroke-width="2px" stroke-linejoin="round">5<tspan x="9.0234375" dy="0">&ZeroWidthSpace;</tspan></tspan>5</text></g><g class="highcharts-label highcharts-data-label highcharts-data-label-color-0" data-z-index="1" filter="none" transform="translate(549,170)"><text x="9.0234375" data-z-index="1" y="16" text-anchor="middle" style="font-size: 0.7em; font-weight: bold; fill: rgb(0, 0, 0);"><tspan class="highcharts-text-outline" fill="#FFFFFF" stroke="#FFFFFF" stroke-width="2px" stroke-linejoin="round">2<tspan x="9.0234375" dy="0">&ZeroWidthSpace;</tspan></tspan>2</text></g><g class="highcharts-label highcharts-data-label highcharts-data-label-color-0" data-z-index="1" filter="none" transform="translate(576,158)"><text x="9.0234375" data-z-index="1" y="16" text-anchor="middle" style="font-size: 0.7em; font-weight: bold; fill: rgb(0, 0, 0);"><tspan class="highcharts-text-outline" fill="#FFFFFF" stroke="#FFFFFF" stroke-width="2px" stroke-linejoin="round">8<tspan x="9.0234375" dy="0">&ZeroWidthSpace;</tspan></tspan>8</text></g><g class="highcharts-label highcharts-data-label highcharts-data-label-color-0" data-z-index="1" filter="none" transform="translate(601,146)"><text x="12.25390625" data-z-index="1" y="16" text-anchor="middle" style="font-size: 0.7em; font-weight: bold; fill: rgb(0, 0, 0);"><tspan class="highcharts-text-outline" fill="#FFFFFF" stroke="#FFFFFF" stroke-width="2px" stroke-linejoin="round">14<tspan x="12.25390625" dy="0">&ZeroWidthSpace;</tspan></tspan>14</text></g><g class="highcharts-label highcharts-data-label highcharts-data-label-color-0" data-z-index="1" filter="none" transform="translate(631,172)"><text x="9.0234375" data-z-index="1" y="16" text-anchor="middle" style="font-size: 0.7em; font-weight: bold; fill: rgb(0, 0, 0);"><tspan class="highcharts-text-outline" fill="#FFFFFF" stroke="#FFFFFF" stroke-width="2px" stroke-linejoin="round">1<tspan x="9.0234375" dy="0">&ZeroWidthSpace;</tspan></tspan>1</text></g><g class="highcharts-label highcharts-data-label highcharts-data-label-color-0" data-z-index="1" filter="none" transform="translate(658,170)"><text x="9.0234375" data-z-index="1" y="16" text-anchor="middle" style="font-size: 0.7em; font-weight: bold; fill: rgb(0, 0, 0);"><tspan class="highcharts-text-outline" fill="#FFFFFF" stroke="#FFFFFF" stroke-width="2px" stroke-linejoin="round">2<tspan x="9.0234375" dy="0">&ZeroWidthSpace;</tspan></tspan>2</text></g><g class="highcharts-label highcharts-data-label highcharts-data-label-color-0" data-z-index="1" filter="none" transform="translate(685,166)"><text x="9.0234375" data-z-index="1" y="16" text-anchor="middle" style="font-size: 0.7em; font-weight: bold; fill: rgb(0, 0, 0);"><tspan class="highcharts-text-outline" fill="#FFFFFF" stroke="#FFFFFF" stroke-width="2px" stroke-linejoin="round">4<tspan x="9.0234375" dy="0">&ZeroWidthSpace;</tspan></tspan>4</text></g><g class="highcharts-label highcharts-data-label highcharts-data-label-color-0" data-z-index="1" filter="none" transform="translate(712,162)"><text x="9.0234375" data-z-index="1" y="16" text-anchor="middle" style="font-size: 0.7em; font-weight: bold; fill: rgb(0, 0, 0);"><tspan class="highcharts-text-outline" fill="#FFFFFF" stroke="#FFFFFF" stroke-width="2px" stroke-linejoin="round">6<tspan x="9.0234375" dy="0">&ZeroWidthSpace;</tspan></tspan>6</text></g><g class="highcharts-label highcharts-data-label highcharts-data-label-color-0" data-z-index="1" filter="none" transform="translate(740,168)"><text x="9.0234375" data-z-index="1" y="16" text-anchor="middle" style="font-size: 0.7em; font-weight: bold; fill: rgb(0, 0, 0);"><tspan class="highcharts-text-outline" fill="#FFFFFF" stroke="#FFFFFF" stroke-width="2px" stroke-linejoin="round">3<tspan x="9.0234375" dy="0">&ZeroWidthSpace;</tspan></tspan>3</text></g><g class="highcharts-label highcharts-data-label highcharts-data-label-color-0" data-z-index="1" filter="none" transform="translate(767,164)"><text x="9.0234375" data-z-index="1" y="16" text-anchor="middle" style="font-size: 0.7em; font-weight: bold; fill: rgb(0, 0, 0);"><tspan class="highcharts-text-outline" fill="#FFFFFF" stroke="#FFFFFF" stroke-width="2px" stroke-linejoin="round">5<tspan x="9.0234375" dy="0">&ZeroWidthSpace;</tspan></tspan>5</text></g><g class="highcharts-label highcharts-data-label highcharts-data-label-color-0" data-z-index="1" filter="none" transform="translate(794,164)"><text x="9.0234375" data-z-index="1" y="16" text-anchor="middle" style="font-size: 0.7em; font-weight: bold; fill: rgb(0, 0, 0);"><tspan class="highcharts-text-outline" fill="#FFFFFF" stroke="#FFFFFF" stroke-width="2px" stroke-linejoin="round">5<tspan x="9.0234375" dy="0">&ZeroWidthSpace;</tspan></tspan>5</text></g><g class="highcharts-label highcharts-data-label highcharts-data-label-color-0" data-z-index="1" filter="none" transform="translate(821,166)"><text x="9.0234375" data-z-index="1" y="16" text-anchor="middle" style="font-size: 0.7em; font-weight: bold; fill: rgb(0, 0, 0);"><tspan class="highcharts-text-outline" fill="#FFFFFF" stroke="#FFFFFF" stroke-width="2px" stroke-linejoin="round">4<tspan x="9.0234375" dy="0">&ZeroWidthSpace;</tspan></tspan>4</text></g><g class="highcharts-label highcharts-data-label highcharts-data-label-color-0" data-z-index="1" filter="none" transform="translate(849,172)"><text x="9.0234375" data-z-index="1" y="16" text-anchor="middle" style="font-size: 0.7em; font-weight: bold; fill: rgb(0, 0, 0);"><tspan class="highcharts-text-outline" fill="#FFFFFF" stroke="#FFFFFF" stroke-width="2px" stroke-linejoin="round">1<tspan x="9.0234375" dy="0">&ZeroWidthSpace;</tspan></tspan>1</text></g><g class="highcharts-label highcharts-data-label highcharts-data-label-color-0" data-z-index="1" filter="none" transform="translate(876,170)"><text x="9.0234375" data-z-index="1" y="16" text-anchor="middle" style="font-size: 0.7em; font-weight: bold; fill: rgb(0, 0, 0);"><tspan class="highcharts-text-outline" fill="#FFFFFF" stroke="#FFFFFF" stroke-width="2px" stroke-linejoin="round">2<tspan x="9.0234375" dy="0">&ZeroWidthSpace;</tspan></tspan>2</text></g><g class="highcharts-label highcharts-data-label highcharts-data-label-color-0" data-z-index="1" filter="none" transform="translate(903,166)"><text x="9.0234375" data-z-index="1" y="16" text-anchor="middle" style="font-size: 0.7em; font-weight: bold; fill: rgb(0, 0, 0);"><tspan class="highcharts-text-outline" fill="#FFFFFF" stroke="#FFFFFF" stroke-width="2px" stroke-linejoin="round">4<tspan x="9.0234375" dy="0">&ZeroWidthSpace;</tspan></tspan>4</text></g><g class="highcharts-label highcharts-data-label highcharts-data-label-color-0" data-z-index="1" filter="none" transform="translate(930,168)"><text x="9.0234375" data-z-index="1" y="16" text-anchor="middle" style="font-size: 0.7em; font-weight: bold; fill: rgb(0, 0, 0);"><tspan class="highcharts-text-outline" fill="#FFFFFF" stroke="#FFFFFF" stroke-width="2px" stroke-linejoin="round">3<tspan x="9.0234375" dy="0">&ZeroWidthSpace;</tspan></tspan>3</text></g><g class="highcharts-label highcharts-data-label highcharts-data-label-color-0" data-z-index="1" filter="none" transform="translate(957,170)"><text x="9.0234375" data-z-index="1" y="16" text-anchor="middle" style="font-size: 0.7em; font-weight: bold; fill: rgb(0, 0, 0);"><tspan class="highcharts-text-outline" fill="#FFFFFF" stroke="#FFFFFF" stroke-width="2px" stroke-linejoin="round">2<tspan x="9.0234375" dy="0">&ZeroWidthSpace;</tspan></tspan>2</text></g><g class="highcharts-label highcharts-data-label highcharts-data-label-color-0" data-z-index="1" filter="none" transform="translate(985,168)"><text x="9.0234375" data-z-index="1" y="16" text-anchor="middle" style="font-size: 0.7em; font-weight: bold; fill: rgb(0, 0, 0);"><tspan class="highcharts-text-outline" fill="#FFFFFF" stroke="#FFFFFF" stroke-width="2px" stroke-linejoin="round">3<tspan x="9.0234375" dy="0">&ZeroWidthSpace;</tspan></tspan>3</text></g><g class="highcharts-label highcharts-data-label highcharts-data-label-color-0" data-z-index="1" filter="none" transform="translate(1012,172)"><text x="9.0234375" data-z-index="1" y="16" text-anchor="middle" style="font-size: 0.7em; font-weight: bold; fill: rgb(0, 0, 0);"><tspan class="highcharts-text-outline" fill="#FFFFFF" stroke="#FFFFFF" stroke-width="2px" stroke-linejoin="round">1<tspan x="9.0234375" dy="0">&ZeroWidthSpace;</tspan></tspan>1</text></g><g class="highcharts-label highcharts-data-label highcharts-data-label-color-0" data-z-index="1" filter="none" transform="translate(1039,172)"><text x="9.0234375" data-z-index="1" y="16" text-anchor="middle" style="font-size: 0.7em; font-weight: bold; fill: rgb(0, 0, 0);"><tspan class="highcharts-text-outline" fill="#FFFFFF" stroke="#FFFFFF" stroke-width="2px" stroke-linejoin="round">1<tspan x="9.0234375" dy="0">&ZeroWidthSpace;</tspan></tspan>1</text></g><g class="highcharts-label highcharts-data-label highcharts-data-label-color-0" data-z-index="1" filter="none" transform="translate(1066,172)"><text x="9.0234375" data-z-index="1" y="16" text-anchor="middle" style="font-size: 0.7em; font-weight: bold; fill: rgb(0, 0, 0);"><tspan class="highcharts-text-outline" fill="#FFFFFF" stroke="#FFFFFF" stroke-width="2px" stroke-linejoin="round">1<tspan x="9.0234375" dy="0">&ZeroWidthSpace;</tspan></tspan>1</text></g><g class="highcharts-label highcharts-data-label highcharts-data-label-color-0" data-z-index="1" filter="none" transform="translate(1094,166)"><text x="9.0234375" data-z-index="1" y="16" text-anchor="middle" style="font-size: 0.7em; font-weight: bold; fill: rgb(0, 0, 0);"><tspan class="highcharts-text-outline" fill="#FFFFFF" stroke="#FFFFFF" stroke-width="2px" stroke-linejoin="round">4<tspan x="9.0234375" dy="0">&ZeroWidthSpace;</tspan></tspan>4</text></g><g class="highcharts-label highcharts-data-label highcharts-data-label-color-0" data-z-index="1" filter="none" transform="translate(1121,172)"><text x="9.0234375" data-z-index="1" y="16" text-anchor="middle" style="font-size: 0.7em; font-weight: bold; fill: rgb(0, 0, 0);"><tspan class="highcharts-text-outline" fill="#FFFFFF" stroke="#FFFFFF" stroke-width="2px" stroke-linejoin="round">1<tspan x="9.0234375" dy="0">&ZeroWidthSpace;</tspan></tspan>1</text></g><g class="highcharts-label highcharts-data-label highcharts-data-label-color-0" data-z-index="1" filter="none" transform="translate(1148,172)"><text x="9.0234375" data-z-index="1" y="16" text-anchor="middle" style="font-size: 0.7em; font-weight: bold; fill: rgb(0, 0, 0);"><tspan class="highcharts-text-outline" fill="#FFFFFF" stroke="#FFFFFF" stroke-width="2px" stroke-linejoin="round">1<tspan x="9.0234375" dy="0">&ZeroWidthSpace;</tspan></tspan>1</text></g><g class="highcharts-label highcharts-data-label highcharts-data-label-color-0" data-z-index="1" filter="none" transform="translate(1175,172)"><text x="9.0234375" data-z-index="1" y="16" text-anchor="middle" style="font-size: 0.7em; font-weight: bold; fill: rgb(0, 0, 0);"><tspan class="highcharts-text-outline" fill="#FFFFFF" stroke="#FFFFFF" stroke-width="2px" stroke-linejoin="round">1<tspan x="9.0234375" dy="0">&ZeroWidthSpace;</tspan></tspan>1</text></g><g class="highcharts-label highcharts-data-label highcharts-data-label-color-0" data-z-index="1" filter="none" transform="translate(1202,172)"><text x="9.0234375" data-z-index="1" y="16" text-anchor="middle" style="font-size: 0.7em; font-weight: bold; fill: rgb(0, 0, 0);"><tspan class="highcharts-text-outline" fill="#FFFFFF" stroke="#FFFFFF" stroke-width="2px" stroke-linejoin="round">1<tspan x="9.0234375" dy="0">&ZeroWidthSpace;</tspan></tspan>1</text></g></g><g class="highcharts-legend highcharts-no-tooltip" data-z-index="7" text-align="center" transform="translate(587,355)" aria-hidden="true"><rect fill="none" class="highcharts-legend-box" rx="0" ry="0" stroke="#999999" stroke-width="0" filter="none" x="0" y="0" width="134" height="30"></rect><g data-z-index="1"><g><g class="highcharts-legend-item highcharts-column-series highcharts-color-0 highcharts-series-0" data-z-index="1" transform="translate(8,3)"><text x="21" text-anchor="start" data-z-index="2" style="cursor: pointer; font-size: 0.8em; text-decoration: none; fill: rgb(51, 51, 51);" y="17">Member States</text><rect x="2" y="6" rx="6" ry="6" width="12" height="12" fill="#28a745" class="highcharts-point" data-z-index="3"></rect></g></g></g></g><g class="highcharts-axis-labels highcharts-xaxis-labels" data-z-index="7" aria-hidden="true"><text x="89.43953823585507" text-anchor="end" transform="translate(0,0) rotate(-45 89.43953823585507 230)" style="cursor: default; font-size: 0.8em; fill: rgb(51, 51, 51);" y="230" opacity="1">South Sudan</text><text x="116.66176045807507" text-anchor="end" transform="translate(0,0) rotate(-45 116.66176045807507 230)" style="cursor: default; font-size: 0.8em; fill: rgb(51, 51, 51);" y="230" opacity="1">Chad</text><text x="143.88398268030508" text-anchor="end" transform="translate(0,0) rotate(-45 143.88398268030508 230)" style="cursor: default; font-size: 0.8em; fill: rgb(51, 51, 51);" y="230" opacity="1">Japan</text><text x="171.10620490252506" text-anchor="end" transform="translate(0,0) rotate(-45 171.10620490252506 230)" style="cursor: default; font-size: 0.8em; fill: rgb(51, 51, 51);" y="230" opacity="1">DR Congo</text><text x="198.32842712474508" text-anchor="end" transform="translate(0,0) rotate(-45 198.32842712474508 230)" style="cursor: default; font-size: 0.8em; fill: rgb(51, 51, 51);" y="230" opacity="1">Ethiopia</text><text x="225.55064934696506" text-anchor="end" transform="translate(0,0) rotate(-45 225.55064934696506 230)" style="cursor: default; font-size: 0.8em; fill: rgb(51, 51, 51);" y="230" opacity="1">Nigeria</text><text x="252.77287156919508" text-anchor="end" transform="translate(0,0) rotate(-45 252.77287156919508 230)" style="cursor: default; font-size: 0.8em; fill: rgb(51, 51, 51);" y="230" opacity="1">Tanzania</text><text x="279.9950937914151" text-anchor="end" transform="translate(0,0) rotate(-45 279.9950937914151 230)" style="cursor: default; font-size: 0.8em; fill: rgb(51, 51, 51);" y="230" opacity="1">Kenya</text><text x="307.2173160136351" text-anchor="end" transform="translate(0,0) rotate(-45 307.2173160136351 230)" style="cursor: default; font-size: 0.8em; fill: rgb(51, 51, 51);" y="230" opacity="1">Morocco</text><text x="334.43953823585514" text-anchor="end" transform="translate(0,0) rotate(-45 334.43953823585514 230)" style="cursor: default; font-size: 0.8em; fill: rgb(51, 51, 51);" y="230" opacity="1">Zambia</text><text x="361.66176045807515" text-anchor="end" transform="translate(0,0) rotate(-45 361.66176045807515 230)" style="cursor: default; font-size: 0.8em; fill: rgb(51, 51, 51);" y="230" opacity="1">USA</text><text x="388.8839826803051" text-anchor="end" transform="translate(0,0) rotate(-45 388.8839826803051 230)" style="cursor: default; font-size: 0.8em; fill: rgb(51, 51, 51);" y="230" opacity="1">Cote d`Ivoire</text><text x="416.1062049025251" text-anchor="end" transform="translate(0,0) rotate(-45 416.1062049025251 230)" style="cursor: default; font-size: 0.8em; fill: rgb(51, 51, 51);" y="230" opacity="1">Guinea-Bissau</text><text x="443.32842712474513" text-anchor="end" transform="translate(0,0) rotate(-45 443.32842712474513 230)" style="cursor: default; font-size: 0.8em; fill: rgb(51, 51, 51);" y="230" opacity="1">Cameroon</text><text x="470.55064934696514" text-anchor="end" transform="translate(0,0) rotate(-45 470.55064934696514 230)" style="cursor: default; font-size: 0.8em; fill: rgb(51, 51, 51);" y="230" opacity="1">Zimbabwe</text><text x="497.7728715691951" text-anchor="end" transform="translate(0,0) rotate(-45 497.7728715691951 230)" style="cursor: default; font-size: 0.8em; fill: rgb(51, 51, 51);" y="230" opacity="1">Rwanda</text><text x="524.9950937914151" text-anchor="end" transform="translate(0,0) rotate(-45 524.9950937914151 230)" style="cursor: default; font-size: 0.8em; fill: rgb(51, 51, 51);" y="230" opacity="1">Egypt</text><text x="552.2173160136351" text-anchor="end" transform="translate(0,0) rotate(-45 552.2173160136351 230)" style="cursor: default; font-size: 0.8em; fill: rgb(51, 51, 51);" y="230" opacity="1">Uganda</text><text x="579.4395382358551" text-anchor="end" transform="translate(0,0) rotate(-45 579.4395382358551 230)" style="cursor: default; font-size: 0.8em; fill: rgb(51, 51, 51);" y="230" opacity="1">South Africa</text><text x="606.6617604580752" text-anchor="end" transform="translate(0,0) rotate(-45 606.6617604580752 230)" style="cursor: default; font-size: 0.8em; fill: rgb(51, 51, 51);" y="230" opacity="1">Burkina Faso</text><text x="633.8839826803052" text-anchor="end" transform="translate(0,0) rotate(-45 633.8839826803052 230)" style="cursor: default; font-size: 0.8em; fill: rgb(51, 51, 51);" y="230" opacity="1">Togo</text><text x="661.1062049025252" text-anchor="end" transform="translate(0,0) rotate(-45 661.1062049025252 230)" style="cursor: default; font-size: 0.8em; fill: rgb(51, 51, 51);" y="230" opacity="1">Ghana</text><text x="688.3284271247451" text-anchor="end" transform="translate(0,0) rotate(-45 688.3284271247451 230)" style="cursor: default; font-size: 0.8em; fill: rgb(51, 51, 51);" y="230" opacity="1">Burundi</text><text x="715.5506493469651" text-anchor="end" transform="translate(0,0) rotate(-45 715.5506493469651 230)" style="cursor: default; font-size: 0.8em; fill: rgb(51, 51, 51);" y="230" opacity="1">Sierra Leone</text><text x="742.7728715691951" text-anchor="end" transform="translate(0,0) rotate(-45 742.7728715691951 230)" style="cursor: default; font-size: 0.8em; fill: rgb(51, 51, 51);" y="230" opacity="1">Benin</text><text x="769.9950937914151" text-anchor="end" transform="translate(0,0) rotate(-45 769.9950937914151 230)" style="cursor: default; font-size: 0.8em; fill: rgb(51, 51, 51);" y="230" opacity="1">Mali</text><text x="797.2173160136351" text-anchor="end" transform="translate(0,0) rotate(-45 797.2173160136351 230)" style="cursor: default; font-size: 0.8em; fill: rgb(51, 51, 51);" y="230" opacity="1">Malawi</text><text x="824.4395382358551" text-anchor="end" transform="translate(0,0) rotate(-45 824.4395382358551 230)" style="cursor: default; font-size: 0.8em; fill: rgb(51, 51, 51);" y="230" opacity="1">Congo Republic</text><text x="851.6617604580752" text-anchor="end" transform="translate(0,0) rotate(-45 851.6617604580752 230)" style="cursor: default; font-size: 0.8em; fill: rgb(51, 51, 51);" y="230" opacity="1">Gabon</text><text x="878.8839826803052" text-anchor="end" transform="translate(0,0) rotate(-45 878.8839826803052 230)" style="cursor: default; font-size: 0.8em; fill: rgb(51, 51, 51);" y="230" opacity="1">Gambia</text><text x="906.1062049025252" text-anchor="end" transform="translate(0,0) rotate(-45 906.1062049025252 230)" style="cursor: default; font-size: 0.8em; fill: rgb(51, 51, 51);" y="230" opacity="1">Liberia</text><text x="933.3284271247451" text-anchor="end" transform="translate(0,0) rotate(-45 933.3284271247451 230)" style="cursor: default; font-size: 0.8em; fill: rgb(51, 51, 51);" y="230" opacity="1">Central African Republic</text><text x="960.5506493469651" text-anchor="end" transform="translate(0,0) rotate(-45 960.5506493469651 230)" style="cursor: default; font-size: 0.8em; fill: rgb(51, 51, 51);" y="230" opacity="1">Djibouti</text><text x="987.7728715691951" text-anchor="end" transform="translate(0,0) rotate(-45 987.7728715691951 230)" style="cursor: default; font-size: 0.8em; fill: rgb(51, 51, 51);" y="230" opacity="1">Namibia</text><text x="1014.9950937914352" text-anchor="end" transform="translate(0,0) rotate(-45 1014.9950937914352 230)" style="cursor: default; font-size: 0.8em; fill: rgb(51, 51, 51);" y="230" opacity="1">Algeria</text><text x="1042.2173160136351" text-anchor="end" transform="translate(0,0) rotate(-45 1042.2173160136351 230)" style="cursor: default; font-size: 0.8em; fill: rgb(51, 51, 51);" y="230" opacity="1">Guinea</text><text x="1069.439538235835" text-anchor="end" transform="translate(0,0) rotate(-45 1069.439538235835 230)" style="cursor: default; font-size: 0.8em; fill: rgb(51, 51, 51);" y="230" opacity="1">Eswatini</text><text x="1096.6617604580351" text-anchor="end" transform="translate(0,0) rotate(-45 1096.6617604580351 230)" style="cursor: default; font-size: 0.8em; fill: rgb(51, 51, 51);" y="230" opacity="1">Botswana</text><text x="1123.883982680335" text-anchor="end" transform="translate(0,0) rotate(-45 1123.883982680335 230)" style="cursor: default; font-size: 0.8em; fill: rgb(51, 51, 51);" y="230" opacity="1">Angola</text><text x="1151.1062049025352" text-anchor="end" transform="translate(0,0) rotate(-45 1151.1062049025352 230)" style="cursor: default; font-size: 0.8em; fill: rgb(51, 51, 51);" y="230" opacity="1">Sudan</text><text x="1178.328427124735" text-anchor="end" transform="translate(0,0) rotate(-45 1178.328427124735 230)" style="cursor: default; font-size: 0.8em; fill: rgb(51, 51, 51);" y="230" opacity="1">Senegal</text><text x="1205.5506493469352" text-anchor="end" transform="translate(0,0) rotate(-45 1205.5506493469352 230)" style="cursor: default; font-size: 0.8em; fill: rgb(51, 51, 51);" y="230" opacity="1">Tunisia</text><text x="1232.7728715692351" text-anchor="end" transform="translate(0,0) rotate(-45 1232.7728715692351 230)" style="cursor: default; font-size: 0.8em; fill: rgb(51, 51, 51);" y="230" opacity="1">Libya</text><text x="1259.9950937914352" text-anchor="end" transform="translate(0,0) rotate(-45 1259.9950937914352 230)" style="cursor: default; font-size: 0.8em; fill: rgb(51, 51, 51);" y="230" opacity="1">Somalia</text><text x="1287.2173160136351" text-anchor="end" transform="translate(0,0) rotate(-45 1287.2173160136351 230)" style="cursor: default; font-size: 0.8em; fill: rgb(51, 51, 51);" y="230" opacity="1">Lesotho</text></g><g class="highcharts-axis-labels highcharts-yaxis-labels" data-z-index="7" aria-hidden="true"><text x="58" text-anchor="end" transform="translate(0,0)" style="cursor: default; font-size: 0.8em; fill: rgb(51, 51, 51);" y="212" opacity="1">0</text><text x="58" text-anchor="end" transform="translate(0,0)" style="cursor: default; font-size: 0.8em; fill: rgb(51, 51, 51);" y="162" opacity="1">25</text><text x="58" text-anchor="end" transform="translate(0,0)" style="cursor: default; font-size: 0.8em; fill: rgb(51, 51, 51);" y="113" opacity="1">50</text><text x="58" text-anchor="end" transform="translate(0,0)" style="cursor: default; font-size: 0.8em; fill: rgb(51, 51, 51);" y="64" opacity="1">75</text><text x="58" text-anchor="end" transform="translate(0,0)" style="cursor: default; font-size: 0.8em; fill: rgb(51, 51, 51);" y="15" opacity="1">100</text></g></svg><div aria-hidden="false" class="highcharts-a11y-proxy-container-after" style="top: 0px; left: 0px; white-space: nowrap; position: absolute;"><div class="highcharts-a11y-proxy-group highcharts-a11y-proxy-group-zoom"></div><div class="highcharts-a11y-proxy-group highcharts-a11y-proxy-group-legend" aria-label="Toggle series visibility, Chart" role="region"><ul role="list"><li style="list-style: none;"><button class="highcharts-a11y-proxy-element" tabindex="-1" aria-pressed="true" aria-label="Show Member States" style="border-width: 0px; background-color: transparent; cursor: pointer; outline: none; opacity: 0.001; z-index: 999; overflow: hidden; padding: 0px; margin: 0px; display: block; position: absolute; width: 116.414px; height: 15px; left: 597px; top: 363px;"></button></li></ul></div><div class="highcharts-a11y-proxy-group highcharts-a11y-proxy-group-chartMenu"><button class="highcharts-a11y-proxy-element highcharts-no-tooltip" aria-label="View chart menu, Chart" aria-expanded="false" title="Chart context menu" style="border-width: 0px; background-color: transparent; cursor: pointer; outline: none; opacity: 0.001; z-index: 999; overflow: hidden; padding: 0px; margin: 0px; display: block; position: absolute; width: 28px; height: 28px; left: 1271px; top: 6px;"></button></div></div></div><div id="highcharts-screen-reader-region-after-3" aria-hidden="false" style="position: relative;"><div aria-hidden="false" style="position: absolute; width: 1px; height: 1px; overflow: hidden; white-space: nowrap; clip: rect(1px, 1px, 1px, 1px); margin-top: -3px; opacity: 0.01;"><div id="highcharts-end-of-chart-marker-3" class="highcharts-exit-anchor" tabindex="0" aria-hidden="false">End of interactive chart.</div></div></div></div>
          </figure>
        </div>
      </div>
    </div>
  </div>
</div>
<!--end row-->

<div class="row">
  <div class="col-12 col-lg-12 d-flex">
    <div class="card rounded-1 w-100">
      <div class="card-body">
        <div class="d-flex align-items-center">
          <div>
            <h6 class="mb-0">Staff Birthdays</h6>
          </div>
        </div>
        <div>
          
    <ul class="nav nav-tabs" id="myTab" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link active" id="home-tab" data-bs-toggle="tab" data-bs-target="#home" type="button" role="tab" aria-controls="home" aria-selected="true">Today</button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="profile-tab" data-bs-toggle="tab" data-bs-target="#profile" type="button" role="tab" aria-controls="profile" aria-selected="false" tabindex="-1">Tomorrow</button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="contact-tab" data-bs-toggle="tab" data-bs-target="#contact" type="button" role="tab" aria-controls="contact" aria-selected="false" tabindex="-1">Next 7 days</button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="month-tab" data-bs-toggle="tab" data-bs-target="#month" type="button" role="tab" aria-controls="month" aria-selected="false" tabindex="-1">Next 30 days</button>
        </li>
    </ul>
    <div class="tab-content" id="myTabContent">
        <div class="tab-pane fade show active" id="home" role="tabpanel" aria-labelledby="home-tab">
            <div class="table-responsive">
                <h3 style="text-align: center;">Today</h3>
                <div id="DataTables_Table_0_wrapper" class="dataTables_wrapper dt-bootstrap5 no-footer"><div class="dt-buttons btn-group">   <button class="btn btn-outline-secondary buttons-csv buttons-html5" tabindex="0" aria-controls="DataTables_Table_0" type="button"><span>CSV</span></button> <button class="btn btn-outline-secondary buttons-pdf buttons-html5" tabindex="0" aria-controls="DataTables_Table_0" type="button"><span>PDF</span></button> <button class="btn btn-outline-secondary buttons-collection dropdown-toggle buttons-page-length" tabindex="0" aria-controls="DataTables_Table_0" type="button" aria-haspopup="true"><span>Show 25 rows</span></button> </div><div id="DataTables_Table_0_filter" class="dataTables_filter"><label>Search:<input type="search" class="form-control form-control-sm" placeholder="" aria-controls="DataTables_Table_0"></label></div><table class="table mydata table-bordered table-striped dataTable no-footer" id="DataTables_Table_0" role="grid" aria-describedby="DataTables_Table_0_info">
                    <thead>
                        <tr role="row"><th class="sorting_asc" tabindex="0" aria-controls="DataTables_Table_0" rowspan="1" colspan="1" aria-sort="ascending" aria-label="#: activate to sort column descending" style="width: 21.2969px;">#</th><th class="sorting" tabindex="0" aria-controls="DataTables_Table_0" rowspan="1" colspan="1" aria-label="Title: activate to sort column ascending" style="width: 53.7031px;">Title</th><th class="sorting" tabindex="0" aria-controls="DataTables_Table_0" rowspan="1" colspan="1" aria-label="Name: activate to sort column ascending" style="width: 202.883px;">Name</th><th class="sorting" tabindex="0" aria-controls="DataTables_Table_0" rowspan="1" colspan="1" aria-label="Photo: activate to sort column ascending" style="width: 83.484px;">Photo</th><th class="sorting" tabindex="0" aria-controls="DataTables_Table_0" rowspan="1" colspan="1" aria-label="Grade: activate to sort column ascending" style="width: 67.8516px;">Grade</th><th class="sorting" tabindex="0" aria-controls="DataTables_Table_0" rowspan="1" colspan="1" aria-label="DOB: activate to sort column ascending" style="width: 122.398px;">DOB</th><th class="sorting" tabindex="0" aria-controls="DataTables_Table_0" rowspan="1" colspan="1" aria-label="Age: activate to sort column ascending" style="width: 47.4609px;">Age</th><th class="sorting" tabindex="0" aria-controls="DataTables_Table_0" rowspan="1" colspan="1" aria-label="Gender: activate to sort column ascending" style="width: 81.5859px;">Gender</th><th class="sorting" tabindex="0" aria-controls="DataTables_Table_0" rowspan="1" colspan="1" aria-label="Job: activate to sort column ascending" style="width: 176.031px;">Job</th><th class="sorting" tabindex="0" aria-controls="DataTables_Table_0" rowspan="1" colspan="1" aria-label="Duty Station: activate to sort column ascending" style="width: 132.859px;">Duty Station</th><th class="sorting" tabindex="0" aria-controls="DataTables_Table_0" rowspan="1" colspan="1" aria-label="Division: activate to sort column ascending" style="width: 119.445px;">Division</th></tr>
                    </thead>
                    <tbody>
                        <!-- Loop through data and display rows -->

                                                    
                                            <tr role="row" class="odd">
                                <td class="sorting_1">1</td>
                                <td>Ms</td>
                                <td><a href="http://localhost/staff/staff/staff_contracts/366">Mgemezulu Towela </a></td>
                                <td>
                                    <div class="avatar-placeholder" style="background-color:#09adeb; color: #fff; 
                                  width: 50px; height: 50px; display: flex; align-items: center; border: 1px solid #fff;
                                  justify-content: center; border-radius: 50%; font-size: 18px; font-weight: bold;">
                                      MT
            </div>                                    
                                </td>
                                <td>P2</td>
                                <td>1989-05-16</td>
                                <td>36</td>
                                <td>Female</td>
                                <td>Monitoring and…</td>
                                <td>Zimbabwe</td>
                                <td>Executive…</td>

                            </tr></tbody>
                </table><div class="dataTables_info" id="DataTables_Table_0_info" role="status" aria-live="polite">Showing 1 to 1 of 1 entries</div><div class="dataTables_paginate paging_simple_numbers" id="DataTables_Table_0_paginate"><ul class="pagination"><li class="paginate_button page-item previous disabled" id="DataTables_Table_0_previous"><a href="#" aria-controls="DataTables_Table_0" data-dt-idx="0" tabindex="0" class="page-link">Prev</a></li><li class="paginate_button page-item active"><a href="#" aria-controls="DataTables_Table_0" data-dt-idx="1" tabindex="0" class="page-link">1</a></li><li class="paginate_button page-item next disabled" id="DataTables_Table_0_next"><a href="#" aria-controls="DataTables_Table_0" data-dt-idx="2" tabindex="0" class="page-link">Next</a></li></ul></div></div>
            </div>
        </div>
        <div class="tab-pane fade" id="profile" role="tabpanel" aria-labelledby="profile-tab">
            <div class="table-responsive">
                <h3 style="text-align: center;">Tomorrow</h3>
                <div id="DataTables_Table_1_wrapper" class="dataTables_wrapper dt-bootstrap5 no-footer"><div class="dt-buttons btn-group">   <button class="btn btn-outline-secondary buttons-csv buttons-html5" tabindex="0" aria-controls="DataTables_Table_1" type="button"><span>CSV</span></button> <button class="btn btn-outline-secondary buttons-pdf buttons-html5" tabindex="0" aria-controls="DataTables_Table_1" type="button"><span>PDF</span></button> <button class="btn btn-outline-secondary buttons-collection dropdown-toggle buttons-page-length" tabindex="0" aria-controls="DataTables_Table_1" type="button" aria-haspopup="true"><span>Show 25 rows</span></button> </div><div id="DataTables_Table_1_filter" class="dataTables_filter"><label>Search:<input type="search" class="form-control form-control-sm" placeholder="" aria-controls="DataTables_Table_1"></label></div><table class="table mydata table-bordered table-striped dataTable no-footer" id="DataTables_Table_1" role="grid" aria-describedby="DataTables_Table_1_info">
                    <thead>
                        <tr role="row"><th class="sorting_asc" tabindex="0" aria-controls="DataTables_Table_1" rowspan="1" colspan="1" aria-sort="ascending" aria-label="#: activate to sort column descending" style="width: 0px;">#</th><th class="sorting" tabindex="0" aria-controls="DataTables_Table_1" rowspan="1" colspan="1" aria-label="Title: activate to sort column ascending" style="width: 0px;">Title</th><th class="sorting" tabindex="0" aria-controls="DataTables_Table_1" rowspan="1" colspan="1" aria-label="Name: activate to sort column ascending" style="width: 0px;">Name</th><th class="sorting" tabindex="0" aria-controls="DataTables_Table_1" rowspan="1" colspan="1" aria-label="Photo: activate to sort column ascending" style="width: 0px;">Photo</th><th class="sorting" tabindex="0" aria-controls="DataTables_Table_1" rowspan="1" colspan="1" aria-label="Grade: activate to sort column ascending" style="width: 0px;">Grade</th><th class="sorting" tabindex="0" aria-controls="DataTables_Table_1" rowspan="1" colspan="1" aria-label="DOB: activate to sort column ascending" style="width: 0px;">DOB</th><th class="sorting" tabindex="0" aria-controls="DataTables_Table_1" rowspan="1" colspan="1" aria-label="Age: activate to sort column ascending" style="width: 0px;">Age</th><th class="sorting" tabindex="0" aria-controls="DataTables_Table_1" rowspan="1" colspan="1" aria-label="Gender: activate to sort column ascending" style="width: 0px;">Gender</th><th class="sorting" tabindex="0" aria-controls="DataTables_Table_1" rowspan="1" colspan="1" aria-label="Job: activate to sort column ascending" style="width: 0px;">Job</th><th class="sorting" tabindex="0" aria-controls="DataTables_Table_1" rowspan="1" colspan="1" aria-label="Duty Station: activate to sort column ascending" style="width: 0px;">Duty Station</th><th class="sorting" tabindex="0" aria-controls="DataTables_Table_1" rowspan="1" colspan="1" aria-label="Division: activate to sort column ascending" style="width: 0px;">Division</th></tr>
                    </thead>
                    <tbody>
                        <!-- Loop through data and display rows -->

                                                    
                                                    
                                            <tr role="row" class="odd">
                                <td class="sorting_1">0</td>
                                <td>Ms</td>
                                <td><a href="http://localhost/staff/staff/staff_contracts/63">Hussein  Farha  Elduma Abdalla</a></td>
           
                                <td>
                                    <div class="avatar-placeholder" style="background-color:#5e9016; color: #fff; 
                                  width: 50px; height: 50px; display: flex; align-items: center; border: 1px solid #fff;
                                  justify-content: center; border-radius: 50%; font-size: 18px; font-weight: bold;">
                                      HF
            </div>                                    
                                </td>
                                <td>P2</td>
                                <td>1988-05-17</td>
                                <td>36</td>
                                <td>Female</td>
                                <td>Technical Officer…</td>
                                <td>Addis Ababa</td>
                                <td>Public Health Institutes…</td>

                            </tr><tr role="row" class="even">
                                <td class="sorting_1">1</td>
                                <td>Ms</td>
                                <td><a href="http://localhost/staff/staff/staff_contracts/366">Mgemezulu Towela </a></td>
           
                                <td>
                                    <div class="avatar-placeholder" style="background-color:#09adeb; color: #fff; 
                                  width: 50px; height: 50px; display: flex; align-items: center; border: 1px solid #fff;
                                  justify-content: center; border-radius: 50%; font-size: 18px; font-weight: bold;">
                                      MT
            </div>                                    
                                </td>
                                <td>P2</td>
                                <td>1989-05-16</td>
                                <td>36</td>
                                <td>Female</td>
                                <td>Monitoring and…</td>
                                <td>Zimbabwe</td>
                                <td>Executive Office</td>

                            </tr></tbody>
                </table><div class="dataTables_info" id="DataTables_Table_1_info" role="status" aria-live="polite">Showing 1 to 2 of 2 entries</div><div class="dataTables_paginate paging_simple_numbers" id="DataTables_Table_1_paginate"><ul class="pagination"><li class="paginate_button page-item previous disabled" id="DataTables_Table_1_previous"><a href="#" aria-controls="DataTables_Table_1" data-dt-idx="0" tabindex="0" class="page-link">Prev</a></li><li class="paginate_button page-item active"><a href="#" aria-controls="DataTables_Table_1" data-dt-idx="1" tabindex="0" class="page-link">1</a></li><li class="paginate_button page-item next disabled" id="DataTables_Table_1_next"><a href="#" aria-controls="DataTables_Table_1" data-dt-idx="2" tabindex="0" class="page-link">Next</a></li></ul></div></div>
            </div>
        </div>
        <div class="tab-pane fade" id="contact" role="tabpanel" aria-labelledby="contact-tab">
            <div class="table-responsive">
                <h3 style="text-align: center;">Next 7 days</h3>
                <div id="DataTables_Table_2_wrapper" class="dataTables_wrapper dt-bootstrap5 no-footer"><div class="dt-buttons btn-group">   <button class="btn btn-outline-secondary buttons-csv buttons-html5" tabindex="0" aria-controls="DataTables_Table_2" type="button"><span>CSV</span></button> <button class="btn btn-outline-secondary buttons-pdf buttons-html5" tabindex="0" aria-controls="DataTables_Table_2" type="button"><span>PDF</span></button> <button class="btn btn-outline-secondary buttons-collection dropdown-toggle buttons-page-length" tabindex="0" aria-controls="DataTables_Table_2" type="button" aria-haspopup="true"><span>Show 25 rows</span></button> </div><div id="DataTables_Table_2_filter" class="dataTables_filter"><label>Search:<input type="search" class="form-control form-control-sm" placeholder="" aria-controls="DataTables_Table_2"></label></div><table class="table mydata table-bordered table-striped dataTable no-footer" id="DataTables_Table_2" role="grid" aria-describedby="DataTables_Table_2_info">
                    <thead>
                        <tr role="row"><th class="sorting_asc" tabindex="0" aria-controls="DataTables_Table_2" rowspan="1" colspan="1" aria-sort="ascending" aria-label="#: activate to sort column descending" style="width: 0px;">#</th><th class="sorting" tabindex="0" aria-controls="DataTables_Table_2" rowspan="1" colspan="1" aria-label="Title: activate to sort column ascending" style="width: 0px;">Title</th><th class="sorting" tabindex="0" aria-controls="DataTables_Table_2" rowspan="1" colspan="1" aria-label="Name: activate to sort column ascending" style="width: 0px;">Name</th><th class="sorting" tabindex="0" aria-controls="DataTables_Table_2" rowspan="1" colspan="1" aria-label="Photo: activate to sort column ascending" style="width: 0px;">Photo</th><th class="sorting" tabindex="0" aria-controls="DataTables_Table_2" rowspan="1" colspan="1" aria-label="Grade: activate to sort column ascending" style="width: 0px;">Grade</th><th class="sorting" tabindex="0" aria-controls="DataTables_Table_2" rowspan="1" colspan="1" aria-label="DOB: activate to sort column ascending" style="width: 0px;">DOB</th><th class="sorting" tabindex="0" aria-controls="DataTables_Table_2" rowspan="1" colspan="1" aria-label="Age: activate to sort column ascending" style="width: 0px;">Age</th><th class="sorting" tabindex="0" aria-controls="DataTables_Table_2" rowspan="1" colspan="1" aria-label="Gender: activate to sort column ascending" style="width: 0px;">Gender</th><th class="sorting" tabindex="0" aria-controls="DataTables_Table_2" rowspan="1" colspan="1" aria-label="Job: activate to sort column ascending" style="width: 0px;">Job</th><th class="sorting" tabindex="0" aria-controls="DataTables_Table_2" rowspan="1" colspan="1" aria-label="Duty Station: activate to sort column ascending" style="width: 0px;">Duty Station</th><th class="sorting" tabindex="0" aria-controls="DataTables_Table_2" rowspan="1" colspan="1" aria-label="Division: activate to sort column ascending" style="width: 0px;">Division</th></tr>
                    </thead>
                    <tbody>
                        <!-- Loop through data and display rows -->

                                                    
                                                    
                                                    
                                                    
                                                    
                                                    
                                                    
                                                    
                                                    
                                            <tr role="row" class="odd">
                                <td class="sorting_1">1</td>
                                <td>Ms</td>
                                <td><a href="http://localhost/staff/staff/staff_contracts/63">Hussein  Farha  Elduma Abdalla</a></td>
           
                                <td>
                                    <div class="avatar-placeholder" style="background-color:#5e9016; color: #fff; 
                                  width: 50px; height: 50px; display: flex; align-items: center; border: 1px solid #fff;
                                  justify-content: center; border-radius: 50%; font-size: 18px; font-weight: bold;">
                                      HF
            </div>                                    
                                </td>
                                <td>P2</td>
                                <td>1988-05-17</td>
                                <td>36</td>
                                <td>Female</td>
                                <td>Technical Officer…</td>
                                <td>Addis Ababa</td>
                                <td>Public…</td>

                            </tr><tr role="row" class="even">
                                <td class="sorting_1">2</td>
                                <td>Mr</td>
                                <td><a href="http://localhost/staff/staff/staff_contracts/116">MM  Musa  Sowe </a></td>
           
                                <td>
                                    <div class="avatar-placeholder" style="background-color:#e529fe; color: #fff; 
                                  width: 50px; height: 50px; display: flex; align-items: center; border: 1px solid #fff;
                                  justify-content: center; border-radius: 50%; font-size: 18px; font-weight: bold;">
                                      MM
            </div>                                    
                                </td>
                                <td>P3</td>
                                <td>1967-05-18</td>
                                <td>57</td>
                                <td>Male</td>
                                <td>Senior Technical…</td>
                                <td>Addis Ababa</td>
                                <td>Emergency…</td>

                            </tr><tr role="row" class="odd">
                                <td class="sorting_1">3</td>
                                <td>Ms</td>
                                <td><a href="http://localhost/staff/staff/staff_contracts/315">Masaba Beatrice </a></td>
           
                                <td>
                                    <div class="avatar-placeholder" style="background-color:#d76192; color: #fff; 
                                  width: 50px; height: 50px; display: flex; align-items: center; border: 1px solid #fff;
                                  justify-content: center; border-radius: 50%; font-size: 18px; font-weight: bold;">
                                      MB
            </div>                                    
                                </td>
                                <td>P2</td>
                                <td>1993-05-18</td>
                                <td>31</td>
                                <td>Female</td>
                                <td>HR Officer - AVoHC…</td>
                                <td>Addis Ababa</td>
                                <td>Directorate…</td>

                            </tr><tr role="row" class="even">
                                <td class="sorting_1">4</td>
                                <td>Ms</td>
                                <td><a href="http://localhost/staff/staff/staff_contracts/326">Timah Sidonie </a></td>
           
                                <td>
                                    <div class="avatar-placeholder" style="background-color:#e1a06e; color: #fff; 
                                  width: 50px; height: 50px; display: flex; align-items: center; border: 1px solid #fff;
                                  justify-content: center; border-radius: 50%; font-size: 18px; font-weight: bold;">
                                      TS
            </div>                                    
                                </td>
                                <td>GSA5</td>
                                <td>1992-05-20</td>
                                <td>32</td>
                                <td>Female</td>
                                <td>Administrative…</td>
                                <td>Addis Ababa</td>
                                <td>Executive…</td>

                            </tr><tr role="row" class="odd">
                                <td class="sorting_1">5</td>
                                <td>Ms</td>
                                <td><a href="http://localhost/staff/staff/staff_contracts/331">Dzigbordi Gertrude Agbeshie</a></td>
           
                                <td>
                                    <div class="avatar-placeholder" style="background-color:#5fd66f; color: #fff; 
                                  width: 50px; height: 50px; display: flex; align-items: center; border: 1px solid #fff;
                                  justify-content: center; border-radius: 50%; font-size: 18px; font-weight: bold;">
                                      DG
            </div>                                    
                                </td>
                                <td>P2</td>
                                <td>1991-05-19</td>
                                <td>33</td>
                                <td>Female</td>
                                <td>Monitoring and…</td>
                                <td>Addis Ababa</td>
                                <td>Planning…</td>

                            </tr><tr role="row" class="even">
                                <td class="sorting_1">6</td>
                                <td>Ms</td>
                                <td><a href="http://localhost/staff/staff/staff_contracts/366">Mgemezulu Towela </a></td>
           
                                <td>
                                    <div class="avatar-placeholder" style="background-color:#09adeb; color: #fff; 
                                  width: 50px; height: 50px; display: flex; align-items: center; border: 1px solid #fff;
                                  justify-content: center; border-radius: 50%; font-size: 18px; font-weight: bold;">
                                      MT
            </div>                                    
                                </td>
                                <td>P2</td>
                                <td>1989-05-16</td>
                                <td>36</td>
                                <td>Female</td>
                                <td>Monitoring and…</td>
                                <td>Zimbabwe</td>
                                <td>Executive…</td>

                            </tr><tr role="row" class="odd">
                                <td class="sorting_1">7</td>
                                <td>Mr</td>
                                <td><a href="http://localhost/staff/staff/staff_contracts/413">Makoni Munyaradzi </a></td>
           
                                <td>
                                    <div class="avatar-placeholder" style="background-color:#7a6b42; color: #fff; 
                                  width: 50px; height: 50px; display: flex; align-items: center; border: 1px solid #fff;
                                  justify-content: center; border-radius: 50%; font-size: 18px; font-weight: bold;">
                                      MM
            </div>                                    
                                </td>
                                <td>P3</td>
                                <td>1977-05-22</td>
                                <td>47</td>
                                <td>Male</td>
                                <td>Senior Science…</td>
                                <td>Addis Ababa</td>
                                <td>Executive…</td>

                            </tr><tr role="row" class="even">
                                <td class="sorting_1">8</td>
                                <td>Ms</td>
                                <td><a href="http://localhost/staff/staff/staff_contracts/508">Nviri Florence </a></td>
           
                                <td>
                                    <div class="avatar-placeholder" style="background-color:#c71d25; color: #fff; 
                                  width: 50px; height: 50px; display: flex; align-items: center; border: 1px solid #fff;
                                  justify-content: center; border-radius: 50%; font-size: 18px; font-weight: bold;">
                                      NF
            </div>                                    
                                </td>
                                <td>P5</td>
                                <td>1972-05-21</td>
                                <td>52</td>
                                <td>Female</td>
                                <td>Head of Internal…</td>
                                <td>Addis Ababa</td>
                                <td></td>

                            </tr><tr role="row" class="odd">
                                <td class="sorting_1">9</td>
                                <td>Ms</td>
                                <td><a href="http://localhost/staff/staff/staff_contracts/564">Magaya Paidamoyo </a></td>
           
                                <td>
                                    <div class="avatar-placeholder" style="background-color:#d946e2; color: #fff; 
                                  width: 50px; height: 50px; display: flex; align-items: center; border: 1px solid #fff;
                                  justify-content: center; border-radius: 50%; font-size: 18px; font-weight: bold;">
                                      MP
            </div>                                    
                                </td>
                                <td>P4</td>
                                <td>1981-05-19</td>
                                <td>43</td>
                                <td>Female</td>
                                <td>Principal Technical…</td>
                                <td>Addis Ababa</td>
                                <td>Directorate…</td>

                            </tr></tbody>
                </table><div class="dataTables_info" id="DataTables_Table_2_info" role="status" aria-live="polite">Showing 1 to 9 of 9 entries</div><div class="dataTables_paginate paging_simple_numbers" id="DataTables_Table_2_paginate"><ul class="pagination"><li class="paginate_button page-item previous disabled" id="DataTables_Table_2_previous"><a href="#" aria-controls="DataTables_Table_2" data-dt-idx="0" tabindex="0" class="page-link">Prev</a></li><li class="paginate_button page-item active"><a href="#" aria-controls="DataTables_Table_2" data-dt-idx="1" tabindex="0" class="page-link">1</a></li><li class="paginate_button page-item next disabled" id="DataTables_Table_2_next"><a href="#" aria-controls="DataTables_Table_2" data-dt-idx="2" tabindex="0" class="page-link">Next</a></li></ul></div></div>
            </div>
        </div>
        <div class="tab-pane fade" id="month" role="tabpanel" aria-labelledby="month-tab">
            <div class="table-responsive">
                <h3 style="text-align: center;">Next 30 days</h3>
                <div id="DataTables_Table_3_wrapper" class="dataTables_wrapper dt-bootstrap5 no-footer"><div class="dt-buttons btn-group">   <button class="btn btn-outline-secondary buttons-csv buttons-html5" tabindex="0" aria-controls="DataTables_Table_3" type="button"><span>CSV</span></button> <button class="btn btn-outline-secondary buttons-pdf buttons-html5" tabindex="0" aria-controls="DataTables_Table_3" type="button"><span>PDF</span></button> <button class="btn btn-outline-secondary buttons-collection dropdown-toggle buttons-page-length" tabindex="0" aria-controls="DataTables_Table_3" type="button" aria-haspopup="true"><span>Show 25 rows</span></button> </div><div id="DataTables_Table_3_filter" class="dataTables_filter"><label>Search:<input type="search" class="form-control form-control-sm" placeholder="" aria-controls="DataTables_Table_3"></label></div><table class="table mydata table-bordered table-striped dataTable no-footer" id="DataTables_Table_3" role="grid" aria-describedby="DataTables_Table_3_info">
                    <thead>
                        <tr role="row"><th class="sorting_asc" tabindex="0" aria-controls="DataTables_Table_3" rowspan="1" colspan="1" aria-sort="ascending" aria-label="#: activate to sort column descending" style="width: 0px;">#</th><th class="sorting" tabindex="0" aria-controls="DataTables_Table_3" rowspan="1" colspan="1" aria-label="Title: activate to sort column ascending" style="width: 0px;">Title</th><th class="sorting" tabindex="0" aria-controls="DataTables_Table_3" rowspan="1" colspan="1" aria-label="Name: activate to sort column ascending" style="width: 0px;">Name</th><th class="sorting" tabindex="0" aria-controls="DataTables_Table_3" rowspan="1" colspan="1" aria-label="Photo: activate to sort column ascending" style="width: 0px;">Photo</th><th class="sorting" tabindex="0" aria-controls="DataTables_Table_3" rowspan="1" colspan="1" aria-label="Grade: activate to sort column ascending" style="width: 0px;">Grade</th><th class="sorting" tabindex="0" aria-controls="DataTables_Table_3" rowspan="1" colspan="1" aria-label="DOB: activate to sort column ascending" style="width: 0px;">DOB</th><th class="sorting" tabindex="0" aria-controls="DataTables_Table_3" rowspan="1" colspan="1" aria-label="Age: activate to sort column ascending" style="width: 0px;">Age</th><th class="sorting" tabindex="0" aria-controls="DataTables_Table_3" rowspan="1" colspan="1" aria-label="Gender: activate to sort column ascending" style="width: 0px;">Gender</th><th class="sorting" tabindex="0" aria-controls="DataTables_Table_3" rowspan="1" colspan="1" aria-label="Job: activate to sort column ascending" style="width: 0px;">Job</th><th class="sorting" tabindex="0" aria-controls="DataTables_Table_3" rowspan="1" colspan="1" aria-label="Duty Station: activate to sort column ascending" style="width: 0px;">Duty Station</th><th class="sorting" tabindex="0" aria-controls="DataTables_Table_3" rowspan="1" colspan="1" aria-label="Division: activate to sort column ascending" style="width: 0px;">Division</th></tr>
                    </thead>
                    <tbody>
                        <!-- Loop through data and display rows -->

                         
                        
                         
                        
                         
                        
                         
                        
                         
                        
                         
                        
                         
                        
                         
                        
                         
                        
                         
                        
                         
                        
                         
                        
                         
                        
                         
                        
                         
                        
                         
                        
                         
                        
                         
                        
                         
                        
                         
                        
                         
                        
                         
                        
                         
                        
                         
                        
                         
                        
                         
                        
                         
                        
                                            <tr role="row" class="odd">

                                <td class="sorting_1">1</td>
                                <td>Ms.</td>
                                <td><a href="http://localhost/staff/staff/staff_contracts/13">Tadesse Haregewein  </a></td>
           
                                <td>
                                    <div class="avatar-placeholder" style="background-color:#03e96d; color: #fff; 
                                  width: 50px; height: 50px; display: flex; align-items: center; border: 1px solid #fff;
                                  justify-content: center; border-radius: 50%; font-size: 18px; font-weight: bold;">
                                      TH
            </div>                                    
                                </td>
                                <td>GSA4</td>
                                <td>1968-06-01</td>
                                <td>56</td>
                                <td>Female</td>
                                <td>Secretary</td>
                                <td>Addis Ababa</td>
                                <td>Office…</td>

                            </tr><tr role="row" class="even">

                                <td class="sorting_1">2</td>
                                <td>Dr</td>
                                <td><a href="http://localhost/staff/staff/staff_contracts/23">Aragaw  Merawi  </a></td>
           
                                <td>
                                    <div class="avatar-placeholder" style="background-color:#ecee5c; color: #fff; 
                                  width: 50px; height: 50px; display: flex; align-items: center; border: 1px solid #fff;
                                  justify-content: center; border-radius: 50%; font-size: 18px; font-weight: bold;">
                                      AM
            </div>                                    
                                </td>
                                <td>P5</td>
                                <td>1979-05-24</td>
                                <td>45</td>
                                <td>Male</td>
                                <td>HOD - Surveillance…</td>
                                <td>Addis Ababa</td>
                                <td>Surveillance…</td>

                            </tr><tr role="row" class="odd">

                                <td class="sorting_1">3</td>
                                <td>Ms</td>
                                <td><a href="http://localhost/staff/staff/staff_contracts/63">Hussein  Farha  Elduma Abdalla</a></td>
           
                                <td>
                                    <div class="avatar-placeholder" style="background-color:#5e9016; color: #fff; 
                                  width: 50px; height: 50px; display: flex; align-items: center; border: 1px solid #fff;
                                  justify-content: center; border-radius: 50%; font-size: 18px; font-weight: bold;">
                                      HF
            </div>                                    
                                </td>
                                <td>P2</td>
                                <td>1988-05-17</td>
                                <td>36</td>
                                <td>Female</td>
                                <td>Technical Officer…</td>
                                <td>Addis Ababa</td>
                                <td>Public…</td>

                            </tr><tr role="row" class="even">

                                <td class="sorting_1">4</td>
                                <td>Mr</td>
                                <td><a href="http://localhost/staff/staff/staff_contracts/72">Ntibarigera Roger  </a></td>
           
                                <td>
                                    <div class="avatar-placeholder" style="background-color:#8a734c; color: #fff; 
                                  width: 50px; height: 50px; display: flex; align-items: center; border: 1px solid #fff;
                                  justify-content: center; border-radius: 50%; font-size: 18px; font-weight: bold;">
                                      NR
            </div>                                    
                                </td>
                                <td>P2</td>
                                <td>1984-05-31</td>
                                <td>40</td>
                                <td>Male</td>
                                <td>Supply Chain Management…</td>
                                <td>Addis Ababa</td>
                                <td>Emergency…</td>

                            </tr><tr role="row" class="odd">

                                <td class="sorting_1">5</td>
                                <td>Dr</td>
                                <td><a href="http://localhost/staff/staff/staff_contracts/79">Sonko  Ibrahima  </a></td>
           
                                <td>
                                    <div class="avatar-placeholder" style="background-color:#0e9774; color: #fff; 
                                  width: 50px; height: 50px; display: flex; align-items: center; border: 1px solid #fff;
                                  justify-content: center; border-radius: 50%; font-size: 18px; font-weight: bold;">
                                      SI
            </div>                                    
                                </td>
                                <td>P2</td>
                                <td>1985-06-09</td>
                                <td>39</td>
                                <td>Male</td>
                                <td>Planning Officer…</td>
                                <td>Addis Ababa</td>
                                <td>Emergency…</td>

                            </tr><tr role="row" class="even">

                                <td class="sorting_1">6</td>
                                <td>Ms</td>
                                <td><a href="http://localhost/staff/staff/staff_contracts/101">Maria  Dativa  Aliddeki</a></td>
           
                                <td>
                                    <div class="avatar-placeholder" style="background-color:#aa5742; color: #fff; 
                                  width: 50px; height: 50px; display: flex; align-items: center; border: 1px solid #fff;
                                  justify-content: center; border-radius: 50%; font-size: 18px; font-weight: bold;">
                                      MD
            </div>                                    
                                </td>
                                <td>P2</td>
                                <td>1986-05-27</td>
                                <td>38</td>
                                <td>Female</td>
                                <td>Regional Event…</td>
                                <td>Nairobi</td>
                                <td>Eastern…</td>

                            </tr><tr role="row" class="odd">

                                <td class="sorting_1">7</td>
                                <td>Mr</td>
                                <td><a href="http://localhost/staff/staff/staff_contracts/116">MM  Musa  Sowe </a></td>
           
                                <td>
                                    <div class="avatar-placeholder" style="background-color:#e529fe; color: #fff; 
                                  width: 50px; height: 50px; display: flex; align-items: center; border: 1px solid #fff;
                                  justify-content: center; border-radius: 50%; font-size: 18px; font-weight: bold;">
                                      MM
            </div>                                    
                                </td>
                                <td>P3</td>
                                <td>1967-05-18</td>
                                <td>57</td>
                                <td>Male</td>
                                <td>Senior Technical…</td>
                                <td>Addis Ababa</td>
                                <td>Emergency…</td>

                            </tr><tr role="row" class="even">

                                <td class="sorting_1">8</td>
                                <td>Dr</td>
                                <td><a href="http://localhost/staff/staff/staff_contracts/139">Kabwe Patrick </a></td>
           
                                <td>
                                    <div class="avatar-placeholder" style="background-color:#c410d2; color: #fff; 
                                  width: 50px; height: 50px; display: flex; align-items: center; border: 1px solid #fff;
                                  justify-content: center; border-radius: 50%; font-size: 18px; font-weight: bold;">
                                      KP
            </div>                                    
                                </td>
                                <td>P2</td>
                                <td>1980-06-06</td>
                                <td>44</td>
                                <td>Male</td>
                                <td>Technical Officer…</td>
                                <td>Addis Ababa</td>
                                <td>Centre…</td>

                            </tr><tr role="row" class="odd">

                                <td class="sorting_1">9</td>
                                <td>Mr.</td>
                                <td><a href="http://localhost/staff/staff/staff_contracts/160">Akalu Eskinder </a></td>
           
                                <td>
                                    <div class="avatar-placeholder" style="background-color:#4cc0ce; color: #fff; 
                                  width: 50px; height: 50px; display: flex; align-items: center; border: 1px solid #fff;
                                  justify-content: center; border-radius: 50%; font-size: 18px; font-weight: bold;">
                                      AE
            </div>                                    
                                </td>
                                <td>GSA5</td>
                                <td>1980-06-10</td>
                                <td>44</td>
                                <td>Male</td>
                                <td>Custom Clearing…</td>
                                <td>Addis Ababa</td>
                                <td>Directorate…</td>

                            </tr><tr role="row" class="even">

                                <td class="sorting_1">10</td>
                                <td>Mr.</td>
                                <td><a href="http://localhost/staff/staff/staff_contracts/174">Duga Alemayehu </a></td>
           
                                <td>
                                    <div class="avatar-placeholder" style="background-color:#fbdf16; color: #fff; 
                                  width: 50px; height: 50px; display: flex; align-items: center; border: 1px solid #fff;
                                  justify-content: center; border-radius: 50%; font-size: 18px; font-weight: bold;">
                                      DA
            </div>                                    
                                </td>
                                <td>P3</td>
                                <td>1987-06-12</td>
                                <td>37</td>
                                <td>Male</td>
                                <td>Senior Technical…</td>
                                <td>Addis Ababa</td>
                                <td>Directorate…</td>

                            </tr><tr role="row" class="odd">

                                <td class="sorting_1">11</td>
                                <td>Mr.</td>
                                <td><a href="http://localhost/staff/staff/staff_contracts/210">Omoniyi Peter Idowu</a></td>
           
                                <td>
                                    <div class="avatar-placeholder" style="background-color:#c62189; color: #fff; 
                                  width: 50px; height: 50px; display: flex; align-items: center; border: 1px solid #fff;
                                  justify-content: center; border-radius: 50%; font-size: 18px; font-weight: bold;">
                                      OP
            </div>                                    
                                </td>
                                <td>P2</td>
                                <td>1984-06-09</td>
                                <td>40</td>
                                <td>Male</td>
                                <td>Technical Officer…</td>
                                <td>Nigeria</td>
                                <td>Western…</td>

                            </tr><tr role="row" class="even">

                                <td class="sorting_1">12</td>
                                <td>Dr. </td>
                                <td><a href="http://localhost/staff/staff/staff_contracts/215">Umar Ahmad </a></td>
           
                                <td>
                                    <div class="avatar-placeholder" style="background-color:#944dde; color: #fff; 
                                  width: 50px; height: 50px; display: flex; align-items: center; border: 1px solid #fff;
                                  justify-content: center; border-radius: 50%; font-size: 18px; font-weight: bold;">
                                      UA
            </div>                                    
                                </td>
                                <td>P3</td>
                                <td>1986-06-06</td>
                                <td>38</td>
                                <td>Male</td>
                                <td>Senior Technical…</td>
                                <td>Abuja</td>
                                <td>Laboratory…</td>

                            </tr><tr role="row" class="odd">

                                <td class="sorting_1">13</td>
                                <td>Mr</td>
                                <td><a href="http://localhost/staff/staff/staff_contracts/265">Tesfaye Biruh Kebede</a></td>
           
                                <td>
                                    <div class="avatar-placeholder" style="background-color:#ffd82b; color: #fff; 
                                  width: 50px; height: 50px; display: flex; align-items: center; border: 1px solid #fff;
                                  justify-content: center; border-radius: 50%; font-size: 18px; font-weight: bold;">
                                      TB
            </div>                                    
                                </td>
                                <td>P2</td>
                                <td>1990-06-04</td>
                                <td>34</td>
                                <td>Male</td>
                                <td>Technical Officer-Vaccines…</td>
                                <td>Addis Ababa</td>
                                <td>Northern…</td>

                            </tr><tr role="row" class="even">

                                <td class="sorting_1">14</td>
                                <td>Mr</td>
                                <td><a href="http://localhost/staff/staff/staff_contracts/274">Mukengere Janvier </a></td>
           
                                <td>
                                    <div class="avatar-placeholder" style="background-color:#7ec93e; color: #fff; 
                                  width: 50px; height: 50px; display: flex; align-items: center; border: 1px solid #fff;
                                  justify-content: center; border-radius: 50%; font-size: 18px; font-weight: bold;">
                                      MJ
            </div>                                    
                                </td>
                                <td>P2</td>
                                <td>1974-06-06</td>
                                <td>50</td>
                                <td>Male</td>
                                <td>Technical Officer-Vaccines…</td>
                                <td>Libereville</td>
                                <td>Central…</td>

                            </tr><tr role="row" class="odd">

                                <td class="sorting_1">15</td>
                                <td>Mr.</td>
                                <td><a href="http://localhost/staff/staff/staff_contracts/279">Kayumba Kizito </a></td>
           
                                <td>
                                    <div class="avatar-placeholder" style="background-color:#873ff9; color: #fff; 
                                  width: 50px; height: 50px; display: flex; align-items: center; border: 1px solid #fff;
                                  justify-content: center; border-radius: 50%; font-size: 18px; font-weight: bold;">
                                      KK
            </div>                                    
                                </td>
                                <td>P2</td>
                                <td>1972-06-11</td>
                                <td>52</td>
                                <td>Male</td>
                                <td>Technical Officer…</td>
                                <td>Libereville</td>
                                <td>Central…</td>

                            </tr><tr role="row" class="even">

                                <td class="sorting_1">16</td>
                                <td>Ms</td>
                                <td><a href="http://localhost/staff/staff/staff_contracts/315">Masaba Beatrice </a></td>
           
                                <td>
                                    <div class="avatar-placeholder" style="background-color:#d76192; color: #fff; 
                                  width: 50px; height: 50px; display: flex; align-items: center; border: 1px solid #fff;
                                  justify-content: center; border-radius: 50%; font-size: 18px; font-weight: bold;">
                                      MB
            </div>                                    
                                </td>
                                <td>P2</td>
                                <td>1993-05-18</td>
                                <td>31</td>
                                <td>Female</td>
                                <td>HR Officer - AVoHC…</td>
                                <td>Addis Ababa</td>
                                <td>Directorate…</td>

                            </tr><tr role="row" class="odd">

                                <td class="sorting_1">17</td>
                                <td>Mr</td>
                                <td><a href="http://localhost/staff/staff/staff_contracts/323">Cubahiro Nobel </a></td>
           
                                <td>
                                    <div class="avatar-placeholder" style="background-color:#7ccfcd; color: #fff; 
                                  width: 50px; height: 50px; display: flex; align-items: center; border: 1px solid #fff;
                                  justify-content: center; border-radius: 50%; font-size: 18px; font-weight: bold;">
                                      CN
            </div>                                    
                                </td>
                                <td>P5</td>
                                <td>1990-06-05</td>
                                <td>34</td>
                                <td>Male</td>
                                <td>Senior Advisor…</td>
                                <td>Addis Ababa</td>
                                <td>Executive…</td>

                            </tr><tr role="row" class="even">

                                <td class="sorting_1">18</td>
                                <td>Ms</td>
                                <td><a href="http://localhost/staff/staff/staff_contracts/326">Timah Sidonie </a></td>
           
                                <td>
                                    <div class="avatar-placeholder" style="background-color:#e1a06e; color: #fff; 
                                  width: 50px; height: 50px; display: flex; align-items: center; border: 1px solid #fff;
                                  justify-content: center; border-radius: 50%; font-size: 18px; font-weight: bold;">
                                      TS
            </div>                                    
                                </td>
                                <td>GSA5</td>
                                <td>1992-05-20</td>
                                <td>32</td>
                                <td>Female</td>
                                <td>Administrative…</td>
                                <td>Addis Ababa</td>
                                <td>Executive…</td>

                            </tr><tr role="row" class="odd">

                                <td class="sorting_1">19</td>
                                <td>Ms</td>
                                <td><a href="http://localhost/staff/staff/staff_contracts/327">Nguedia Venessa Nguka</a></td>
           
                                <td>
                                    <div class="avatar-placeholder" style="background-color:#29ff84; color: #fff; 
                                  width: 50px; height: 50px; display: flex; align-items: center; border: 1px solid #fff;
                                  justify-content: center; border-radius: 50%; font-size: 18px; font-weight: bold;">
                                      NV
            </div>                                    
                                </td>
                                <td>P4</td>
                                <td>1990-06-08</td>
                                <td>34</td>
                                <td>Female</td>
                                <td>Advisor-Finance…</td>
                                <td>Addis Ababa</td>
                                <td>Executive…</td>

                            </tr><tr role="row" class="even">

                                <td class="sorting_1">20</td>
                                <td>Ms</td>
                                <td><a href="http://localhost/staff/staff/staff_contracts/331">Dzigbordi Gertrude Agbeshie</a></td>
           
                                <td>
                                    <div class="avatar-placeholder" style="background-color:#5fd66f; color: #fff; 
                                  width: 50px; height: 50px; display: flex; align-items: center; border: 1px solid #fff;
                                  justify-content: center; border-radius: 50%; font-size: 18px; font-weight: bold;">
                                      DG
            </div>                                    
                                </td>
                                <td>P2</td>
                                <td>1991-05-19</td>
                                <td>33</td>
                                <td>Female</td>
                                <td>Monitoring and…</td>
                                <td>Addis Ababa</td>
                                <td>Planning…</td>

                            </tr><tr role="row" class="odd">

                                <td class="sorting_1">21</td>
                                <td>Ms</td>
                                <td><a href="http://localhost/staff/staff/staff_contracts/366">Mgemezulu Towela </a></td>
           
                                <td>
                                    <div class="avatar-placeholder" style="background-color:#09adeb; color: #fff; 
                                  width: 50px; height: 50px; display: flex; align-items: center; border: 1px solid #fff;
                                  justify-content: center; border-radius: 50%; font-size: 18px; font-weight: bold;">
                                      MT
            </div>                                    
                                </td>
                                <td>P2</td>
                                <td>1989-05-16</td>
                                <td>36</td>
                                <td>Female</td>
                                <td>Monitoring and…</td>
                                <td>Zimbabwe</td>
                                <td>Executive…</td>

                            </tr><tr role="row" class="even">

                                <td class="sorting_1">22</td>
                                <td>Mr</td>
                                <td><a href="http://localhost/staff/staff/staff_contracts/372">Mekonnen Abere </a></td>
           
                                <td>
                                    <div class="avatar-placeholder" style="background-color:#e0778c; color: #fff; 
                                  width: 50px; height: 50px; display: flex; align-items: center; border: 1px solid #fff;
                                  justify-content: center; border-radius: 50%; font-size: 18px; font-weight: bold;">
                                      MA
            </div>                                    
                                </td>
                                <td>P2</td>
                                <td>1972-06-06</td>
                                <td>52</td>
                                <td>Male</td>
                                <td>Epidemiologist</td>
                                <td>Addis Ababa</td>
                                <td>Eastern…</td>

                            </tr><tr role="row" class="odd">

                                <td class="sorting_1">23</td>
                                <td>Mr</td>
                                <td><a href="http://localhost/staff/staff/staff_contracts/413">Makoni Munyaradzi </a></td>
           
                                <td>
                                    <div class="avatar-placeholder" style="background-color:#7a6b42; color: #fff; 
                                  width: 50px; height: 50px; display: flex; align-items: center; border: 1px solid #fff;
                                  justify-content: center; border-radius: 50%; font-size: 18px; font-weight: bold;">
                                      MM
            </div>                                    
                                </td>
                                <td>P3</td>
                                <td>1977-05-22</td>
                                <td>47</td>
                                <td>Male</td>
                                <td>Senior Science…</td>
                                <td>Addis Ababa</td>
                                <td>Executive…</td>

                            </tr><tr role="row" class="even">

                                <td class="sorting_1">24</td>
                                <td>Mr</td>
                                <td><a href="http://localhost/staff/staff/staff_contracts/426">Sibanda Fundani </a></td>
           
                                <td>
                                    <div class="avatar-placeholder" style="background-color:#242936; color: #fff; 
                                  width: 50px; height: 50px; display: flex; align-items: center; border: 1px solid #fff;
                                  justify-content: center; border-radius: 50%; font-size: 18px; font-weight: bold;">
                                      SF
            </div>                                    
                                </td>
                                <td>P2</td>
                                <td>1983-06-12</td>
                                <td>41</td>
                                <td>Male</td>
                                <td>Epidemiologist</td>
                                <td>Zimbabwe</td>
                                <td>Surveillance…</td>

                            </tr><tr role="row" class="odd">

                                <td class="sorting_1">25</td>
                                <td>Ms</td>
                                <td><a href="http://localhost/staff/staff/staff_contracts/443">Kassa Sholaye </a></td>
           
                                <td>
                                    <div class="avatar-placeholder" style="background-color:#55e56f; color: #fff; 
                                  width: 50px; height: 50px; display: flex; align-items: center; border: 1px solid #fff;
                                  justify-content: center; border-radius: 50%; font-size: 18px; font-weight: bold;">
                                      KS
            </div>                                    
                                </td>
                                <td>P2</td>
                                <td>1980-06-01</td>
                                <td>44</td>
                                <td>Female</td>
                                <td>Operations Support…</td>
                                <td>Addis Ababa</td>
                                <td>Emergency…</td>

                            </tr></tbody>
                </table><div class="dataTables_info" id="DataTables_Table_3_info" role="status" aria-live="polite">Showing 1 to 25 of 27 entries</div><div class="dataTables_paginate paging_simple_numbers" id="DataTables_Table_3_paginate"><ul class="pagination"><li class="paginate_button page-item previous disabled" id="DataTables_Table_3_previous"><a href="#" aria-controls="DataTables_Table_3" data-dt-idx="0" tabindex="0" class="page-link">Prev</a></li><li class="paginate_button page-item active"><a href="#" aria-controls="DataTables_Table_3" data-dt-idx="1" tabindex="0" class="page-link">1</a></li><li class="paginate_button page-item "><a href="#" aria-controls="DataTables_Table_3" data-dt-idx="2" tabindex="0" class="page-link">2</a></li><li class="paginate_button page-item next" id="DataTables_Table_3_next"><a href="#" aria-controls="DataTables_Table_3" data-dt-idx="3" tabindex="0" class="page-link">Next</a></li></ul></div></div>
            </div>
        </div>
    </div>

        
        </div>
      </div>
    </div>
  </div>
</div>
<!--end row-->

<script>
  Highcharts.setOptions({
    colors: ['#b4a269', '#28a745', '#6905AD', '#0913AC', '#b4a269', '#a3a3a3']
  });

  var pieColors = (function() {
    var colors = [],
      base = Highcharts.getOptions().colors[0],
      i;

    for (i = 0; i < 10; i += 1) {
      colors.push(Highcharts.color(base).brighten((i - 3) / 7).get());
    }
    return colors;
  }());

  Highcharts.chart('container', {
    chart: {
      plotBackgroundColor: null,
      plotBorderWidth: null,
      plotShadow: false,
      type: 'pie'
    },
    title: {
      text: ''
    },
    tooltip: {
      pointFormat: '{series.name}: <b>{point.percentage:.1f}%</b>'
    },
    accessibility: {
      point: {
        valueSuffix: '%'
      }
    },
    plotOptions: {
      pie: {
        allowPointSelect: true,
        size: '70%',
        cursor: 'pointer',
        dataLabels: {
          enabled: true,
          format: '{point.y:1f}<br><b>{point.name}</b><br>{point.percentage:.1f} %',
          distance: -60,
          filter: {
            property: 'percentage',
            operator: '>',
            value: 4
          },
          style: {
            fontSize: '15px'
          }
        }
      }
    },
    series: [{
      name: 'Percentage',
      data: [{"name":"Female","y":146},{"name":"Male","y":257}]    }],
    credits: {
      enabled: false
    }
  });
</script>

<script>
  Highcharts.setOptions({
    colors: ['#b4a269', '#a3a3a3']
  });

  var pieColors = (function() {
    var colors = [],
      base = Highcharts.getOptions().colors[0],
      i;

    for (i = 0; i < 10; i += 1) {
      colors.push(Highcharts.color(base).brighten((i - 3) / 7).get());
    }
    return colors;
  }());
</script>

<script>
  Highcharts.setOptions({
    colors: ['#28a745', '#ED561B', '#DDDF00', '#24CBE5', '#64E572', '#FF9655', '#FFF263', '#6AF9C4']
  });

  Highcharts.chart('container3', {
    chart: {
      type: 'column'
    },
    title: {
      text: ''
    },
    subtitle: {
      text: ''
    },
    xAxis: {
      categories: ["Regular","Seconded","Fixed Term","Consultancy ","ALD","Fellowship"],
      crosshair: true
    },
    yAxis: {
      min: 0,
      title: {
        text: 'Total Staff'
      }
    },
    plotOptions: {
      column: {
        dataLabels: {
          enabled: true
        },
        pointPadding: 0.2,
        borderWidth: 0
      }
    },
    series: [{
      name: 'Contract Types',
      data: [34,282,28,55,3,1]    }],
    credits: {
      enabled: false
    }
  });
</script>

<script>
  Highcharts.setOptions({
    colors: ['#28a745', '#ED561B', '#DDDF00', '#24CBE5', '#64E572', '#FF9655', '#FFF263', '#6AF9C4']
  });

  Highcharts.chart('container4', {
    chart: {
      type: 'column'
    },
    title: {
      text: ''
    },
    subtitle: {
      text: ''
    },
    xAxis: {
      categories: ["Directorate of Administration ","Policy and Health Diplomacy","Centre for Primary Healthcare","Executive Office","Office of the Director General","Public Health Institutes and Research","Office of the Deputy Director General","Southern RCC","Directorate of Finance","Planning Reporting and Accountability","Surveillance and Disease Intelligence ","Health Economics and Financing","Supply Chain Management","Emergency Preparedness and Response","Local Manufacturing of Health Commodities","Legal Affairs and Dispute Settlement","Laboratory Networks and Systems","Disease Control and Prevention","PIU","Central RCC","Eastern RCC","Directorate of Communication and Public Information","Directorate of Science and Innovation","Digital Health and Information Systems","Western RCC","Directorate of External Relations and Strategic Engagements","Northern RCC","IMST - External"],
      crosshair: true
    },
    yAxis: {
      min: 0,
      title: {
        text: 'Total Staff'
      }
    },
    plotOptions: {
      column: {
        dataLabels: {
          enabled: true
        },
        pointPadding: 0,
        borderWidth: 0
      }
    },
    series: [{
      name: 'Divisions',
      data: [30,4,29,24,2,16,2,22,13,15,16,4,5,22,5,6,16,2,9,23,34,16,10,11,28,8,12,16]    }],
    credits: {
      enabled: false
    }
  });
</script>

<script>
  Highcharts.setOptions({
    colors: ['#28a745', '#ED561B', '#DDDF00', '#24CBE5', '#64E572', '#FF9655', '#FFF263', '#6AF9C4']
  });

  Highcharts.chart('container5', {
    chart: {
      type: 'column'
    },
    title: {
      text: ''
    },
    subtitle: {
      text: ''
    },
    xAxis: {
      categories: ["South Sudan","Chad","Japan","DR Congo","Ethiopia","Nigeria","Tanzania","Kenya","Morocco","Zambia","USA","Cote d`Ivoire","Guinea-Bissau","Cameroon","Zimbabwe","Rwanda","Egypt","Uganda","South Africa","Burkina Faso","Togo","Ghana","Burundi","Sierra Leone","Benin","Mali","Malawi","Congo Republic","Gabon","Gambia","Liberia","Central African Republic","Djibouti","Namibia","Algeria","Guinea","Eswatini","Botswana","Angola","Sudan","Senegal","Tunisia","Libya","Somalia","Lesotho"],
      crosshair: true
    },
    yAxis: {
      min: 0,
      title: {
        text: 'Total Staff'
      }
    },
    plotOptions: {
      column: {
        dataLabels: {
          enabled: true
        },
        pointPadding: 0.2,
        borderWidth: 0
      }
    },
    series: [{
      name: 'Member States',
      data: [10,3,4,29,75,51,9,41,1,11,5,2,1,21,17,7,5,21,5,5,2,8,14,1,2,4,6,3,5,5,4,1,2,4,3,2,3,1,1,1,4,1,1,1,1]    }],
    credits: {
      enabled: false
    }
  });
</script></div>
</div>

@endsection
