@include('admin.layouts.parts.header')
<body class="nav-md">
<div class="container body">
    <div class="main_container">
        <div class="col-md-3 left_col">
            <div class="left_col scroll-view">
                <div class="navbar nav_title" style="border: 0;">
                    <a href="{{ url('admin/home') }}" class="site_title"><i class="panelIcon home"></i>
                    <span>Administrare</span>
                    </a>
                </div>
                <div class="clearfix"></div>
                <br>

            @include('admin.layouts.parts.sidebar')

                <!-- /menu footer buttons -->
                <div class="sidebar-footer hidden-small">
                    <a href="{{ url('/') }}" target="_blank" data-toggle="tooltip" data-placement="top" title="Vezi site">
                        <span class="glyphicon glyphicon-eye-open" aria-hidden="true"></span>
                    </a>
                    <a href="{{ url('admin/settings') }}" data-toggle="tooltip" data-placement="top" title="Setari">
                        <span class="glyphicon glyphicon-cog" aria-hidden="true"></span>
                    </a>
                    <a href="{{ url('admin/home/account') }}" data-toggle="tooltip" data-placement="top" title="Profil">
                        <span class="glyphicon glyphicon-user" aria-hidden="true"></span>
                    </a>
                    <a data-toggle="tooltip" data-placement="top" title="Logout" href="{{ url('admin/logout') }}"
                       onclick="event.preventDefault(); document.getElementById('logout-form').submit();">
                        <span class="glyphicon glyphicon-off" aria-hidden="true"></span>
                    </a>
                </div>
                <!-- /menu footer buttons -->
            </div>
        </div>

        <!-- top navigation -->
        <div class="top_nav">
            <div class="nav_menu">
                <nav>
                    <div class="nav toggle">
                        <a id="menu_toggle"><i class="fa fa-bars"></i></a>
                    </div>

                    <ul class="nav navbar-nav navbar-right">
                        <li class="">
                            <a href="javascript:;" class="user-profile dropdown-toggle" data-toggle="dropdown" aria-expanded="false">
                                <img src="{{ asset('assets/admin/images/admin.jpg') }}" alt="">{{ Auth::user()->name }}
                                <span class=" fa fa-angle-down"></span>
                            </a>
                            <ul class="dropdown-menu dropdown-usermenu pull-right">
                                <li><a href="{{ url('admin/home/account') }}"><i class="fa fa-user pull-right"></i> Profil</a></li>
                                <li>
                                    <a href="{{ url('admin/logout') }}"
                                       onclick="event.preventDefault();
                                            document.getElementById('logout-form').submit();">
                                        <i class="fa fa-sign-out pull-right"></i> Log Out</a>
                                    <form id="logout-form" action="{{ url('admin/logout') }}" method="POST" style="display: none;">
                                        {{ csrf_field() }}
                                    </form>
                                </li>
                            </ul>
                        </li>
                        @if( config('settings.magazin') === true )
                        <li role="presentation" class="dropdown">
                            <a href="{{ url('admin/shop/orders') }}" class="dropdown-toggle info-number"  aria-expanded="false">
                                @if( $newOrders != 0 )
                                        Aveti comenzi noi
                                        <i class="fa fa-bell-o animated swing infinite"></i>
                                        <span class="badge bg-green">{{ $newOrders }}</span>
                                    @else
                                    <i class="fa fa-bell-slash-o"></i>
                                @endif
                            </a>
                        </li>
                        @endif
                    </ul>
                </nav>
            </div>
        </div>
        <!-- /top navigation -->

        <!-- page content -->
        <div class="right_col" role="main">
            <div class="">
                <div class="row">
                    <div class="col-md-12 col-sm-12 col-xs-12">
                        <div class="x_panel">
                            <div class="x_title">
                                <h2>@yield('section-title','section-title')</h2>

                                <div class="clearfix"></div>
                            </div>
                            <div class="x_content">
                                <div class="row">
                                    <div class="col-md-7 col-sm-12 col-xs-12 col-md-offset-1">
                                        @if( ! defined('NOERRORS') )
                                            @include('errors.errors')
                                        @endif
                                        @if(session()->has('mesaj'))
                                            @include('admin.layouts.parts.messages')
                                        @endif
                                        @if(session()->has('aborted'))
                                            @include('admin.layouts.parts.abortedMessage')
                                        @endif
                                        @yield('section-content','section-content to add...')
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <!-- /page content -->
@include('admin.layouts.parts.footer')