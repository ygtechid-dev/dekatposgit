<!DOCTYPE html>
<html dir="@if( Config::get('app.locale') == 'ar' || $general_setting->is_rtl){{'rtl'}}@endif">

<head>
  <meta charset="utf-8">
  <meta http-equiv="X-UA-Compatible" content="IE=edge">
  @if(!config('database.connections.saleprosaas_landlord'))
  @php($app_favicon = !empty($general_setting->favicon) ? $general_setting->favicon : $general_setting->site_logo)
  <link rel="icon" type="image/png" href="{{url('logo/'.$app_favicon)}}" />
  <title>{{$general_setting->site_title}}</title>
  <meta name="description" content="">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="robots" content="all,follow">
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <link rel="manifest" href="{{url('manifest.json')}}">
  <!-- Bootstrap CSS-->
  <link rel="stylesheet" href="<?php echo asset('vendor/bootstrap/css/bootstrap.min.css') ?>" type="text/css">
  <link rel="preload" href="<?php echo asset('vendor/bootstrap-toggle/css/bootstrap-toggle.min.css') ?>" as="style" onload="this.onload=null;this.rel='stylesheet'">
  <noscript>
    <link href="<?php echo asset('vendor/bootstrap-toggle/css/bootstrap-toggle.min.css') ?>" rel="stylesheet">
  </noscript>
  <link rel="preload" href="<?php echo asset('vendor/bootstrap/css/bootstrap-datepicker.min.css') ?>" as="style" onload="this.onload=null;this.rel='stylesheet'">
  <link rel="preload" href="<?php echo asset('vendor/jquery-timepicker/jquery.timepicker.min.css') ?>" as="style" onload="this.onload=null;this.rel='stylesheet'">
  <noscript>
    <link href="<?php echo asset('vendor/jquery-timepicker/jquery.timepicker.min.css') ?>" rel="stylesheet">
  </noscript>
  <link rel="preload" href="<?php echo asset('vendor/bootstrap/css/awesome-bootstrap-checkbox.css') ?>" as="style" onload="this.onload=null;this.rel='stylesheet'">
  <noscript>
    <link href="<?php echo asset('vendor/bootstrap/css/awesome-bootstrap-checkbox.css') ?>" rel="stylesheet">
  </noscript>
  <link rel="preload" href="<?php echo asset('vendor/bootstrap/css/bootstrap-select.min.css') ?>" as="style" onload="this.onload=null;this.rel='stylesheet'">
  <noscript>
    <link href="<?php echo asset('vendor/bootstrap/css/bootstrap-select.min.css') ?>" rel="stylesheet">
  </noscript>
  <!-- Font Awesome CSS-->
  <link rel="preload" href="<?php echo asset('vendor/font-awesome/css/font-awesome.min.css') ?>" as="style" onload="this.onload=null;this.rel='stylesheet'">
  <noscript>
    <link href="<?php echo asset('vendor/font-awesome/css/font-awesome.min.css') ?>" rel="stylesheet">
  </noscript>
  <!-- Drip icon font-->
  <link rel="preload" href="<?php echo asset('vendor/dripicons/webfont.css') ?>" as="style" onload="this.onload=null;this.rel='stylesheet'">
  <noscript>
    <link href="<?php echo asset('vendor/dripicons/webfont.css') ?>" rel="stylesheet">
  </noscript>

  <!-- jQuery Circle-->
  <link rel="preload" href="<?php echo asset('css/grasp_mobile_progress_circle-1.0.0.min.css') ?>" as="style" onload="this.onload=null;this.rel='stylesheet'">
  <noscript>
    <link href="<?php echo asset('css/grasp_mobile_progress_circle-1.0.0.min.css') ?>" rel="stylesheet">
  </noscript>
  <!-- Custom Scrollbar-->
  <link rel="preload" href="<?php echo asset('vendor/malihu-custom-scrollbar-plugin/jquery.mCustomScrollbar.css') ?>" as="style" onload="this.onload=null;this.rel='stylesheet'">
  <noscript>
    <link href="<?php echo asset('vendor/malihu-custom-scrollbar-plugin/jquery.mCustomScrollbar.css') ?>" rel="stylesheet">
  </noscript>

  @if(Route::current()->getName() != '/')
  <!-- date range stylesheet-->
  <link rel="preload" href="<?php echo asset('vendor/daterange/css/daterangepicker.min.css') ?>" as="style" onload="this.onload=null;this.rel='stylesheet'">
  <noscript>
    <link href="<?php echo asset('vendor/daterange/css/daterangepicker.min.css') ?>" rel="stylesheet">
  </noscript>
  <!-- table sorter stylesheet-->
  <link rel="preload" href="<?php echo asset('vendor/datatable/dataTables.bootstrap4.min.css') ?>" as="style" onload="this.onload=null;this.rel='stylesheet'">
  <noscript>
    <link href="<?php echo asset('vendor/datatable/dataTables.bootstrap4.min.css') ?>" rel="stylesheet">
  </noscript>
  <link rel="preload" href="https://cdn.datatables.net/fixedheader/3.1.6/css/fixedHeader.bootstrap.min.css" as="style" onload="this.onload=null;this.rel='stylesheet'">
  <noscript>
    <link href="https://cdn.datatables.net/fixedheader/3.1.6/css/fixedHeader.bootstrap.min.css" rel="stylesheet">
  </noscript>
  <link rel="preload" href="https://cdn.datatables.net/responsive/2.2.3/css/responsive.bootstrap.min.css" as="style" onload="this.onload=null;this.rel='stylesheet'">
  <noscript>
    <link href="https://cdn.datatables.net/responsive/2.2.3/css/responsive.bootstrap.min.css" rel="stylesheet">
  </noscript>
  @endif

  <link rel="stylesheet" href="<?php echo asset('css/style.default.css') ?>" id="theme-stylesheet" type="text/css">
  <link rel="stylesheet" href="<?php echo asset('css/dropzone.css') ?>">
  <!-- Custom stylesheet - for your changes-->
  <link rel="stylesheet" href="<?php echo asset('css/custom-' . $general_setting->theme) ?>" type="text/css" id="custom-style">

  @if( Config::get('app.locale') == 'ar' || $general_setting->is_rtl)
  <!-- RTL css -->
  <link rel="stylesheet" href="<?php echo asset('vendor/bootstrap/css/bootstrap-rtl.min.css') ?>" type="text/css">
  <link rel="stylesheet" href="<?php echo asset('css/custom-rtl.css') ?>" type="text/css" id="custom-style">
  @endif
  @else
  @php($app_favicon = !empty($general_setting->favicon) ? $general_setting->favicon : $general_setting->site_logo)
  <link rel="icon" type="image/png" href="{{url('../../logo/'.$app_favicon)}}" />
  <title>{{$general_setting->site_title}}</title>
  <meta name="description" content="">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="robots" content="all,follow">
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <link rel="manifest" href="{{url('manifest.json')}}">
  <!-- Bootstrap CSS-->
  <link rel="stylesheet" href="<?php echo asset('../../vendor/bootstrap/css/bootstrap.min.css') ?>" type="text/css">
  <link rel="preload" href="<?php echo asset('../../vendor/bootstrap-toggle/css/bootstrap-toggle.min.css') ?>" as="style" onload="this.onload=null;this.rel='stylesheet'">
  <noscript>
    <link href="<?php echo asset('../../vendor/bootstrap-toggle/css/bootstrap-toggle.min.css') ?>" rel="stylesheet">
  </noscript>
  <link rel="preload" href="<?php echo asset('../../vendor/bootstrap/css/bootstrap-datepicker.min.css') ?>" as="style" onload="this.onload=null;this.rel='stylesheet'">
  <noscript>
    <link href="<?php echo asset('../../vendor/bootstrap/css/bootstrap-datepicker.min.css') ?>" rel="stylesheet">
  </noscript>
  <link rel="preload" href="<?php echo asset('../../vendor/jquery-timepicker/jquery.timepicker.min.css') ?>" as="style" onload="this.onload=null;this.rel='stylesheet'">
  <noscript>
    <link href="<?php echo asset('../../vendor/jquery-timepicker/jquery.timepicker.min.css') ?>" rel="stylesheet">
  </noscript>
  <link rel="preload" href="<?php echo asset('../../vendor/bootstrap/css/awesome-bootstrap-checkbox.css') ?>" as="style" onload="this.onload=null;this.rel='stylesheet'">
  <noscript>
    <link href="<?php echo asset('../../vendor/bootstrap/css/awesome-bootstrap-checkbox.css') ?>" rel="stylesheet">
  </noscript>
  <link rel="preload" href="<?php echo asset('../../vendor/bootstrap/css/bootstrap-select.min.css') ?>" as="style" onload="this.onload=null;this.rel='stylesheet'">
  <noscript>
    <link href="<?php echo asset('../../vendor/bootstrap/css/bootstrap-select.min.css') ?>" rel="stylesheet">
  </noscript>
  <!-- Font Awesome CSS-->
  <link rel="preload" href="<?php echo asset('../../vendor/font-awesome/css/font-awesome.min.css') ?>" as="style" onload="this.onload=null;this.rel='stylesheet'">
  <noscript>
    <link href="<?php echo asset('../../vendor/font-awesome/css/font-awesome.min.css') ?>" rel="stylesheet">
  </noscript>
  <!-- Drip icon font-->
  <link rel="preload" href="<?php echo asset('../../vendor/dripicons/webfont.css') ?>" as="style" onload="this.onload=null;this.rel='stylesheet'">
  <noscript>
    <link href="<?php echo asset('../../vendor/dripicons/webfont.css') ?>" rel="stylesheet">
  </noscript>

  <!-- jQuery Circle-->
  <link rel="preload" href="<?php echo asset('../../css/grasp_mobile_progress_circle-1.0.0.min.css') ?>" as="style" onload="this.onload=null;this.rel='stylesheet'">
  <noscript>
    <link href="<?php echo asset('../../css/grasp_mobile_progress_circle-1.0.0.min.css') ?>" rel="stylesheet">
  </noscript>
  <!-- Custom Scrollbar-->
  <link rel="preload" href="<?php echo asset('../../vendor/malihu-custom-scrollbar-plugin/jquery.mCustomScrollbar.css') ?>" as="style" onload="this.onload=null;this.rel='stylesheet'">
  <noscript>
    <link href="<?php echo asset('../../vendor/malihu-custom-scrollbar-plugin/jquery.mCustomScrollbar.css') ?>" rel="stylesheet">
  </noscript>

  @if(Route::current()->getName() != '/')
  <!-- date range stylesheet-->
  <link rel="preload" href="<?php echo asset('../../vendor/daterange/css/daterangepicker.min.css') ?>" as="style" onload="this.onload=null;this.rel='stylesheet'">
  <noscript>
    <link href="<?php echo asset('../../vendor/daterange/css/daterangepicker.min.css') ?>" rel="stylesheet">
  </noscript>
  <!-- table sorter stylesheet-->
  <link rel="preload" href="<?php echo asset('../../vendor/datatable/dataTables.bootstrap4.min.css') ?>" as="style" onload="this.onload=null;this.rel='stylesheet'">
  <noscript>
    <link href="<?php echo asset('../../vendor/datatable/dataTables.bootstrap4.min.css') ?>" rel="stylesheet">
  </noscript>
  <link rel="preload" href="https://cdn.datatables.net/fixedheader/3.1.6/css/fixedHeader.bootstrap.min.css" as="style" onload="this.onload=null;this.rel='stylesheet'">
  <noscript>
    <link href="https://cdn.datatables.net/fixedheader/3.1.6/css/fixedHeader.bootstrap.min.css" rel="stylesheet">
  </noscript>
  <link rel="preload" href="https://cdn.datatables.net/responsive/2.2.3/css/responsive.bootstrap.min.css" as="style" onload="this.onload=null;this.rel='stylesheet'">
  <noscript>
    <link href="https://cdn.datatables.net/responsive/2.2.3/css/responsive.bootstrap.min.css" rel="stylesheet">
  </noscript>
  @endif

  <link rel="stylesheet" href="<?php echo asset('../../css/style.default.css') ?>" id="theme-stylesheet" type="text/css">
  <link rel="stylesheet" href="<?php echo asset('../../css/dropzone.css') ?>">
  <!-- Custom stylesheet - for your changes-->
  <link rel="stylesheet" href="<?php echo asset('../../css/custom-' . $general_setting->theme) ?>" type="text/css" id="custom-style">

  @if( Config::get('app.locale') == 'ar' || $general_setting->is_rtl)
  <!-- RTL css -->
  <link rel="stylesheet" href="<?php echo asset('../../vendor/bootstrap/css/bootstrap-rtl.min.css') ?>" type="text/css">
  <link rel="stylesheet" href="<?php echo asset('../../css/custom-rtl.css') ?>" type="text/css" id="custom-style">
  @endif
  @endif
  <!-- Google fonts - Roboto -->
  <link rel="preload" href="https://fonts.googleapis.com/css?family=Nunito:400,500,700" as="style" onload="this.onload=null;this.rel='stylesheet'">
  <noscript>
    <link href="https://fonts.googleapis.com/css?family=Nunito:400,500,700" rel="stylesheet">
  </noscript>

  @stack('css')
</head>

<body class="@if($theme == 'dark')dark-mode dripicons-brightness-low @endif  @if(Route::current()->getName() == 'sale.pos') pos-page @endif" onload="myFunction()">
  <div id="loader"></div>
  <!-- Side Navbar -->
  <nav class="side-navbar">
    <span class="brand-big">
      @if($general_setting->site_logo)
      <a href="{{url('/')}}"><img src="{{url('logo', $general_setting->site_logo)}}" width="115"></a>
      @else
      <a href="{{url('/')}}">
        <h1 class="d-inline">{{$general_setting->site_title}}</h1>
      </a>
      @endif
    </span>
    @include('backend.layout.sidebar')
  </nav>

  <div class="page">
    <!-- navbar-->
    @if(Route::current()->getName() != 'sale.pos')
    <header class="container-fluid">
      <nav class="navbar">
        <a id="toggle-btn" href="#" class="menu-btn"><i class="fa fa-bars"> </i></a>

        <ul class="nav-menu list-unstyled d-flex flex-md-row align-items-md-center">
          <div class="dropdown">
            
            <a class="btn-pos btn-sm" type="button" data-toggle="dropdown" aria-expanded="false">
              <i class="dripicons-plus"></i>
            </a>
            <ul class="dropdown-menu">
              <?php
              $category_permission_active = $role_has_permissions_list->where('name', 'category')->first();
              ?>
              @if($category_permission_active)
              <li class="dropdown-item"><a data-toggle="modal" data-target="#category-modal">{{__('file.Add Category')}}</a></li>
              @endif
              <?php
              $add_permission_active = $role_has_permissions_list->where('name', 'products-add')->first();
              ?>
              @if($add_permission_active)
              <li class="dropdown-item"><a href="{{route('products.create')}}">{{__('file.add_product')}}</a></li>
              @endif
              <?php
              $add_permission_active = $role_has_permissions_list->where('name', 'purchases-add')->first();
              ?>
              @if($add_permission_active)
              <li class="dropdown-item"><a href="{{route('purchases.create')}}">{{trans('file.Add Purchase')}}</a></li>
              @endif
              <?php
              $sale_add_permission_active = $role_has_permissions_list->where('name', 'sales-add')->first();
              ?>
              @if($sale_add_permission_active)
              <li class="dropdown-item"><a href="{{route('sales.create')}}">{{trans('file.Add Sale')}}</a></li>
              @endif
              <?php
              $expense_add_permission_active = $role_has_permissions_list->where('name', 'expenses-add')->first();
              ?>
              @if($expense_add_permission_active)
              <li class="dropdown-item"><a data-toggle="modal" data-target="#expense-modal"> {{trans('file.Add Expense')}}</a></li>
              @endif
              <?php
              $quotation_add_permission_active = $role_has_permissions_list->where('name', 'quotes-add')->first();
              ?>
              @if($quotation_add_permission_active)
              <li class="dropdown-item"><a href="{{route('quotations.create')}}">{{trans('file.Add Quotation')}}</a></li>
              @endif
              <?php
              $transfer_add_permission_active = $role_has_permissions_list->where('name', 'transfers-add')->first();
              ?>
              @if($transfer_add_permission_active)
              <li class="dropdown-item"><a href="{{route('transfers.create')}}">{{trans('file.Add Transfer')}}</a></li>
              @endif
              <?php
              $return_add_permission_active = $role_has_permissions_list->where('name', 'returns-add')->first();
              ?>
              @if($return_add_permission_active)
              <li class="dropdown-item"><a href="#" data-toggle="modal" data-target="#add-sale-return"> {{trans('file.Add Return')}}</a></li>
              @endif
              <?php
              $purchase_return_add_permission_active = $role_has_permissions_list->where('name', 'purchase-return-add')->first();
              ?>
              @if($purchase_return_add_permission_active)
              <li class="dropdown-item"><a href="#" data-toggle="modal" data-target="#add-purchase-return"> {{trans('file.Add Purchase Return')}}</a></li>
              @endif
              <?php
              $user_add_permission_active = $role_has_permissions_list->where('name', 'users-add')->first();
              ?>
              @if($user_add_permission_active)
              <li class="dropdown-item"><a href="{{route('user.create')}}">{{trans('file.Add User')}}</a></li>
              @endif
              <?php
              $customer_add_permission_active = $role_has_permissions_list->where('name', 'customers-add')->first();
              ?>
              @if($customer_add_permission_active)
              <li class="dropdown-item"><a href="{{route('customer.create')}}">{{trans('file.Add Customer')}}</a></li>
              @endif
              <?php
              $biller_add_permission_active = $role_has_permissions_list->where('name', 'billers-add')->first();
              ?>
              @if($biller_add_permission_active)
              <li class="dropdown-item"><a href="{{route('biller.create')}}">{{trans('file.Add Biller')}}</a></li>
              @endif
              <?php
              $supplier_add_permission_active = $role_has_permissions_list->where('name', 'suppliers-add')->first();
              ?>
              @if($supplier_add_permission_active)
              <li class="dropdown-item"><a href="{{route('supplier.create')}}">{{trans('file.Add Supplier')}}</a></li>
              @endif
            </ul>
          </div>
          <?php
          $empty_database_permission_active = $role_has_permissions_list->where('name', 'empty_database')->first();

          $sale_add_permission_active = $role_has_permissions_list->where('name', 'sales-add')->first();

          $product_qty_alert_active = $role_has_permissions_list->where('name', 'product-qty-alert')->first();

          $general_setting_permission_active = $role_has_permissions_list->where('name', 'general_setting')->first();
          
          ?>
          @if($sale_add_permission_active)
          <li class="nav-item"><a class="btn-pos btn-sm" href="{{route('sale.pos')}}"><i class="dripicons-shopping-bag"></i><span> POS</span></a></li>
          @endif
          <li class="nav-item"><a id="switch-theme" data-toggle="tooltip" title="{{trans('file.Switch Theme')}}"><i class="dripicons-brightness-max"></i></a></li>
          @if(config('database.connections.saleprosaas_landlord'))
          <li class="nav-item"><a target="_blank" href="{{'https://'.env('CENTRAL_DOMAIN').'/contact-for-renewal?id='.$subdomain}}" data-toggle="tooltip" title="{{trans('file.Renew Subscription')}}"><i class="dripicons-clockwise"></i></a></li>
          @endif
          <li class="nav-item"><a id="btnFullscreen" data-toggle="tooltip" title="{{trans('file.Full Screen')}}"><i class="dripicons-expand"></i></a></li>
          @if(\Auth::user()->role_id <= 2) <li class="nav-item"><a href="{{route('cashRegister.index')}}" data-toggle="tooltip" title="{{trans('file.Cash Register List')}}"><i class="dripicons-archive"></i></a></li>
            @endif
            @if($product_qty_alert_active && ($alert_product + $dso_alert_product_no + \Auth::user()->unreadNotifications->where('data.reminder_date', date('Y-m-d'))->count() ) > 0)
            <li class="nav-item" id="notification-icon">
              <a rel="nofollow" data-toggle="tooltip" title="{{__('Notifications')}}" class="nav-link dropdown-item"><i class="dripicons-bell"></i><span class="badge badge-danger notification-number">{{$alert_product + $dso_alert_product_no + \Auth::user()->unreadNotifications->where('data.reminder_date', date('Y-m-d'))->count()}}</span>
              </a>
              <ul class="right-sidebar">
                <li class="notifications">
                  <a href="{{route('report.qtyAlert')}}" class="btn btn-link"> {{$alert_product}} product exceeds alert quantity</a>
                </li>
                @if($dso_alert_product_no)
                <li class="notifications">
                  <a href="{{route('report.dailySaleObjective')}}" class="btn btn-link"> {{$dso_alert_product_no}} product could not fulfill daily sale objective</a>
                </li>
                @endif
                @foreach(\Auth::user()->unreadNotifications->where('data.reminder_date', date('Y-m-d')) as $key => $notification)
                <li class="notifications">
                  @if($notification->data['document_name'])
                  <a target="_blank" href="{{url('public/documents/notification', $notification->data['document_name'])}}" class="btn btn-link">{{ $notification->data['message'] }}</a>
                  @else
                  <a href="#" class="btn btn-link">{{ $notification->data['message'] }}</a>
                  @endif
                </li>
                @endforeach
              </ul>
            </li>
            @endif
            @if(\Auth::user()->unreadNotifications->where('data.reminder_date', date('Y-m-d'))->count() > 0)
            <li class="nav-item" id="notification-icon">
              <a rel="nofollow" data-toggle="tooltip" title="{{__('Notifications')}}" class="nav-link dropdown-item"><i class="dripicons-bell"></i><span class="badge badge-danger notification-number">{{\Auth::user()->unreadNotifications->where('data.reminder_date', date('Y-m-d'))->count()}}</span>
              </a>
              <ul class="right-sidebar">
                @foreach(\Auth::user()->unreadNotifications->where('data.reminder_date', date('Y-m-d')) as $key => $notification)
                <li class="notifications">
                  @if($notification->data['document_name'])
                  <a target="_blank" href="{{url('public/documents/notification', $notification->data['document_name'])}}" class="btn btn-link">{{ $notification->data['message'] }}</a>
                  @else
                  <a href="#" class="btn btn-link">{{ $notification->data['message'] }}</a>
                  @endif
                </li>
                @endforeach
              </ul>
            </li>
            @endif
            <li class="nav-item">
              <a rel="nofollow" title="{{trans('file.language')}}" data-toggle="tooltip" class="nav-link dropdown-item"><i class="dripicons-web"></i></a>
              <ul class="right-sidebar">
                <li>
                  <a href="{{ url('language_switch/en') }}" class="btn btn-link"> English</a>
                </li>
                <li>
                  <a href="{{ url('language_switch/es') }}" class="btn btn-link"> Español</a>
                </li>
                <li>
                  <a href="{{ url('language_switch/ar') }}" class="btn btn-link"> عربى</a>
                </li>
                <li>
                  <a href="{{ url('language_switch/al') }}" class="btn btn-link"> Albanian</a>
                </li>
                <li>
                  <a href="{{ url('language_switch/s_chinese') }}" class="btn btn-link">中国人</a>
                </li>
                <li>
                  <a href="{{ url('language_switch/t_chinese') }}" class="btn btn-link">中國人</a>
                </li>
                <li>
                  <a href="{{ url('language_switch/pt_BR') }}" class="btn btn-link"> Portuguese</a>
                </li>
                <li>
                  <a href="{{ url('language_switch/fr') }}" class="btn btn-link"> Français</a>
                </li>
                <li>
                  <a href="{{ url('language_switch/de') }}" class="btn btn-link"> Deutsche</a>
                </li>
                <li>
                  <a href="{{ url('language_switch/id') }}" class="btn btn-link"> Bahasa</a>
                </li>
                <li>
                  <a href="{{ url('language_switch/ms') }}" class="btn btn-link"> Malay</a>
                </li>
                <li>
                  <a href="{{ url('language_switch/hi') }}" class="btn btn-link"> हिंदी</a>
                </li>
                <li>
                  <a href="{{ url('language_switch/vi') }}" class="btn btn-link"> Tiếng Việt</a>
                </li>
                <li>
                  <a href="{{ url('language_switch/ru') }}" class="btn btn-link"> русский</a>
                </li>
                <li>
                  <a href="{{ url('language_switch/bg') }}" class="btn btn-link"> български</a>
                </li>
                <li>
                  <a href="{{ url('language_switch/tr') }}" class="btn btn-link"> Türk</a>
                </li>
                <li>
                  <a href="{{ url('language_switch/it') }}" class="btn btn-link"> Italiano</a>
                </li>
                <li>
                  <a href="{{ url('language_switch/nl') }}" class="btn btn-link"> Nederlands</a>
                </li>
                <li>
                  <a href="{{ url('language_switch/lao') }}" class="btn btn-link"> Lao</a>
                </li>
              </ul>
            </li>
            <li class="nav-item">
              <a rel="nofollow" data-toggle="tooltip" class="nav-link dropdown-item"><i class="dripicons-user"></i> <span>{{ucfirst(Auth::user()->name)}}</span> <i class="fa fa-angle-down"></i>
              </a>
              <ul class="right-sidebar">
                <li>
                  <a href="{{route('user.profile', ['id' => Auth::id()])}}"><i class="dripicons-user"></i> {{trans('file.profile')}}</a>
                </li>
                @if($general_setting_permission_active)
                <li>
                  <a href="{{route('setting.general')}}"><i class="dripicons-gear"></i> {{trans('file.settings')}}</a>
                </li>
                @endif
                <li>
                  <a href="{{url('my-transactions/'.date('Y').'/'.date('m'))}}"><i class="dripicons-swap"></i> {{trans('file.My Transaction')}}</a>
                </li>
                @if(Auth::user()->role_id != 5)
                <li>
                  <a href="{{url('holidays/my-holiday/'.date('Y').'/'.date('m'))}}"><i class="dripicons-vibrate"></i> {{trans('file.My Holiday')}}</a>
                </li>
                @endif
                @if($empty_database_permission_active)
                <li>
                  <a onclick="return confirm('Are you sure want to delete? If you do this all of your data will be lost.')" href="{{route('setting.emptyDatabase')}}"><i class="dripicons-stack"></i> {{trans('file.Empty Database')}}</a>
                </li>
                @endif
                <li>
                  <a href="{{ route('logout') }}" onclick="event.preventDefault();
                                        document.getElementById('logout-form').submit();"><i class="dripicons-power"></i>
                    {{trans('file.logout')}}
                  </a>
                  <form id="logout-form" action="{{ route('logout') }}" method="POST" style="display: none;">
                    @csrf
                  </form>
                </li>
              </ul>
            </li>
        </ul>
      </nav>
    </header>
    @endif


    <div style="display:none" id="content" class="animate-bottom">
      @yield('content')
    </div>

    <footer class="main-footer">
      <div class="container-fluid">
        <div class="row">
          <div class="col-sm-12">
            <p>&copy; {{$general_setting->site_title}} | {{trans('file.Developed')}} {{trans('file.By')}} <span class="external">{{$general_setting->developed_by}}</span> | V {{env('VERSION')}}</p>
          </div>
        </div>
      </div>
    </footer>

    <!-- notification modal -->
    <div id="notification-modal" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel" aria-hidden="true" class="modal fade text-left">
      <div role="document" class="modal-dialog">
        <div class="modal-content">
          <div class="modal-header">
            <h5 id="exampleModalLabel" class="modal-title">{{trans('file.Send Notification')}}</h5>
            <button type="button" data-dismiss="modal" aria-label="Close" class="close"><span aria-hidden="true"><i class="dripicons-cross"></i></span></button>
          </div>
          <div class="modal-body">
            <p class="italic"><small>{{trans('file.The field labels marked with * are required input fields')}}.</small></p>
            {!! Form::open(['route' => 'notifications.store', 'method' => 'post', 'files'=> true]) !!}
            <div class="row">
              <?php
              $lims_user_list = DB::table('users')->where([
                ['is_active', true],
                ['id', '!=', \Auth::user()->id]
              ])->get();
              ?>
              <div class="col-md-4 form-group">
                <input type="hidden" name="sender_id" value="{{\Auth::id()}}">
                <label>{{trans('file.User')}} *</label>
                <select name="receiver_id" class="selectpicker form-control" required data-live-search="true" data-live-search-style="begins" title="Select user...">
                  @foreach($lims_user_list as $user)
                  <option value="{{$user->id}}">{{$user->name . ' (' . $user->email. ')'}}</option>
                  @endforeach
                </select>
              </div>
              <div class="col-md-4 form-group">
                <label>{{trans('file.Reminder Date')}}</label>
                <input type="text" name="reminder_date" class="form-control date" value="{{date('d-m-Y')}}">
              </div>
              <div class="col-md-4 form-group">
                <label>{{trans('file.Attach Document')}}</label>
                <input type="file" name="document" class="form-control">
              </div>
              <div class="col-md-12 form-group">
                <label>{{trans('file.Message')}} *</label>
                <textarea rows="5" name="message" class="form-control" required></textarea>
              </div>
            </div>
            <div class="form-group">
              <button type="submit" class="btn btn-primary ">{{trans('file.submit')}}</button>
            </div>
            {{ Form::close() }}
          </div>
        </div>
      </div>
    </div>
    <!-- end notification modal -->

    <!-- Category Modal -->
    <div id="category-modal" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel" aria-hidden="true" class="modal fade text-left">
      <div role="document" class="modal-dialog">
        <div class="modal-content">
          {!! Form::open(['route' => 'category.store', 'method' => 'post', 'files' => true, 'id' => 'category-form']) !!}
          <div class="modal-header">
            <h5 id="exampleModalLabel" class="modal-title">{{trans('file.Add Category')}}</h5>
            <button type="button" data-dismiss="modal" aria-label="Close" class="close"><span aria-hidden="true"><i class="dripicons-cross"></i></span></button>
          </div>
          <div class="modal-body">
            <p class="italic"><small>{{trans('file.The field labels marked with * are required input fields')}}.</small></p>
            <div class="row">
              <div class="col-md-6 form-group">
                <label>{{trans('file.name')}} *</label>
                {{Form::text('name',null,array('required' => 'required', 'class' => 'form-control', 'placeholder' => 'Type category name...'))}}
              </div>
              <div class="col-md-6 form-group">
                <label>{{trans('file.Image')}}</label>
                <input type="file" name="image" class="form-control">
              </div>
              <div class="col-md-6 form-group">
                <label>{{trans('file.Parent Category')}}</label>
                <select name="parent_id" class="form-control selectpicker" id="parent">
                  <option value="">No {{trans('file.parent')}}</option>
                  @foreach($categories_list as $category)
                  <option value="{{$category->id}}">{{$category->name}}</option>
                  @endforeach
                </select>
              </div>
              @if (\Schema::hasColumn('categories', 'woocommerce_category_id'))
              <div class="col-md-6 form-group mt-4">
                <input class="mt-3" name="is_sync_disable" type="checkbox" id="is_sync_disable" value="1">&nbsp; {{trans('file.Disable Woocommerce Sync')}}
              </div>
              @endif

              @if(in_array('ecommerce',explode(',',$general_setting->modules)))
              <div class="col-md-12 mt-3">
                <h6><strong>{{ __('For Website') }}</strong></h6>
                <hr>
              </div>

              <div class="col-md-6 form-group">
                <label>{{ __('Icon') }}</label>
                <input type="file" name="icon" class="form-control">
              </div>
              <div class="col-md-6 form-group">
                <input class="mt-5" type="checkbox" name="featured" id="featured" value="1"> <label>{{ __('List on category dropdown') }}</label>
              </div>
              @endif
            </div>

            @if(in_array('ecommerce',explode(',',$general_setting->modules)))
            <div class="row">
              <div class="col-md-12 mt-3">
                <h6><strong>{{ __('For SEO') }}</strong></h6>
                <hr>
              </div>
              <div class="col-md-12 form-group">
                <label>{{ __('Meta Title') }}</label>
                {{Form::text('page_title',null,array('class' => 'form-control', 'placeholder' => 'Meta Title...'))}}
              </div>
              <div class="col-md-12 form-group">
                <label>{{ __('Meta Description') }}</label>
                {{Form::text('short_description',null,array('class' => 'form-control', 'placeholder' => 'Meta Description...'))}}
              </div>
            </div>
            @endif

            <div class="form-group">
              <input type="hidden" class="category-ajax-check" name="ajax" value="0">
              <button type="submit" class="btn btn-primary category-submit-btn">{{trans('file.submit')}}</button>
            </div>
          </div>
          {{ Form::close() }}
        </div>
      </div>
    </div>
    <!-- Category Modal -->

    <!-- expense modal -->
    <div id="expense-modal" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel" aria-hidden="true" class="modal fade text-left">
      <div role="document" class="modal-dialog">
        <div class="modal-content">
          <div class="modal-header">
            <h5 id="exampleModalLabel" class="modal-title">{{trans('file.Add Expense')}}</h5>
            <button type="button" data-dismiss="modal" aria-label="Close" class="close"><span aria-hidden="true"><i class="dripicons-cross"></i></span></button>
          </div>
          <div class="modal-body">
            <p class="italic"><small>{{trans('file.The field labels marked with * are required input fields')}}.</small></p>
            {!! Form::open(['route' => 'expenses.store', 'method' => 'post', 'files' => true]) !!}
            <?php
            $lims_expense_category_list = DB::table('expense_categories')->where('is_active', true)->get();
            if (Auth::user()->role_id > 2)
              $lims_warehouse_list = DB::table('warehouses')->where([
                ['is_active', true],
                ['id', Auth::user()->warehouse_id]
              ])->get();
            else
              $lims_warehouse_list = DB::table('warehouses')->where('is_active', true)->get();
            $lims_account_list = \App\Models\Account::with('chartOfAccount')->where('is_active', true)->get();
            ?>
            <div class="row">
              <div class="col-md-6 form-group">
                <label>{{trans('file.Date')}}</label>
                <input type="text" name="created_at" class="form-control date" placeholder="Choose date" />
              </div>
              <div class="col-md-6 form-group">
                <label>{{trans('file.Expense Category')}} *</label>
                <select name="expense_category_id" class="selectpicker form-control" required data-live-search="true" data-live-search-style="begins" title="Select Expense Category...">
                  @foreach($lims_expense_category_list as $expense_category)
                  <option value="{{$expense_category->id}}">{{$expense_category->name . ' (' . $expense_category->code. ')'}}</option>
                  @endforeach
                </select>
              </div>
              <div class="col-md-6 form-group">
                <label>{{trans('file.Warehouse')}} *</label>
                <select name="warehouse_id" class="selectpicker form-control" required data-live-search="true" data-live-search-style="begins" title="Select Warehouse...">
                  @foreach($lims_warehouse_list as $warehouse)
                  <option value="{{$warehouse->id}}">{{$warehouse->name}}</option>
                  @endforeach
                </select>
              </div>
              <div class="col-md-6 form-group">
                <label>{{trans('file.Amount')}} *</label>
                <input type="number" name="amount" step="any" required class="form-control">
              </div>
              <div class="col-md-6 form-group">
                <label> {{trans('file.Account')}}</label>
                <select class="form-control selectpicker" name="account_id" data-live-search="true" data-live-search-style="contains" data-size="10">
                  @foreach($lims_account_list as $account)
                  @if($account->is_default)
                  <option selected value="{{$account->id}}">{{$account->coa_label}}</option>
                  @else
                  <option value="{{$account->id}}">{{$account->coa_label}}</option>
                  @endif
                  @endforeach
                </select>
              </div>
            </div>
            <div class="form-group">
              <label>{{trans('file.Note')}}</label>
              <textarea name="note" rows="3" class="form-control"></textarea>
            </div>
            <div class="form-group">
              <label>Lampiran Dokumen</label>
              <input type="file" name="document" class="form-control" accept=".jpg,.jpeg,.png,.gif,.pdf,.csv,.doc,.docx,.xls,.xlsx,.txt">
              <small class="form-text text-muted">Maksimal 10 MB.</small>
            </div>
            <div class="form-group">
              <button type="submit" class="btn btn-primary">{{trans('file.submit')}}</button>
            </div>
            {{ Form::close() }}
          </div>
        </div>
      </div>
    </div>
    <!-- end expense modal -->

    <!-- sale return modal -->
    <div id="add-sale-return" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel" aria-hidden="true" class="modal fade text-left">
      <div role="document" class="modal-dialog">
        <div class="modal-content">
          {!! Form::open(['route' => 'return-sale.create', 'method' => 'get']) !!}
          <div class="modal-header">
            <h5 id="exampleModalLabel" class="modal-title">Add Sale Return</h5>
            <button type="button" data-dismiss="modal" aria-label="Close" class="close"><span aria-hidden="true"><i class="dripicons-cross"></i></span></button>
          </div>
          <div class="modal-body">
            <p class="italic"><small>{{trans('file.The field labels marked with * are required input fields')}}.</small></p>
            <div class="row">
              <div class="col-md-6">
                <div class="form-group">
                  <label>{{trans('file.Sale Reference')}} *</label>
                  <input type="text" name="reference_no" class="form-control">
                </div>
              </div>
            </div>
            {{Form::submit('Submit', ['class' => 'btn btn-primary'])}}
          </div>
          {!! Form::close() !!}
        </div>
      </div>
    </div>
    <!-- end sale return modal -->

    <!-- purchase return modal -->
    <div id="add-purchase-return" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel" aria-hidden="true" class="modal fade text-left">
      <div role="document" class="modal-dialog">
        <div class="modal-content">
          {!! Form::open(['route' => 'return-purchase.create', 'method' => 'get']) !!}
          <div class="modal-header">
            <h5 id="exampleModalLabel" class="modal-title">Add Purchase Return</h5>
            <button type="button" data-dismiss="modal" aria-label="Close" class="close"><span aria-hidden="true"><i class="dripicons-cross"></i></span></button>
          </div>
          <div class="modal-body">
            <p class="italic"><small>{{trans('file.The field labels marked with * are required input fields')}}.</small></p>
            <div class="row">
              <div class="col-md-6">
                <div class="form-group">
                  <label>{{trans('file.Purchase Reference')}} *</label>
                  <input type="text" name="reference_no" class="form-control">
                </div>
              </div>
            </div>
            {{Form::submit('Submit', ['class' => 'btn btn-primary'])}}
          </div>
          {!! Form::close() !!}
        </div>
      </div>
    </div>
    <!-- end purchase return modal -->

    <!-- account modal removed: akun kas/bank sekarang dibuat/dikelola lewat menu
         Akuntansi > Kode Akun (centang "Jadikan akun Kas/Bank"), bukan modal
         "Tambah Akun" terpisah lagi. -->

    <!-- account statement modal -->
    <div id="account-statement-modal" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel" aria-hidden="true" class="modal fade text-left">
      <div role="document" class="modal-dialog">
        <div class="modal-content">
          <div class="modal-header">
            <h5 id="exampleModalLabel" class="modal-title">{{trans('file.Account Statement')}}</h5>
            <button type="button" data-dismiss="modal" aria-label="Close" class="close"><span aria-hidden="true"><i class="dripicons-cross"></i></span></button>
          </div>
          <div class="modal-body">
            <p class="italic"><small>{{trans('file.The field labels marked with * are required input fields')}}.</small></p>
            {!! Form::open(['route' => 'accounts.statement', 'method' => 'post']) !!}
            <div class="row">
              <div class="col-md-6 form-group">
                <label> {{trans('file.Account')}}</label>
                <select class="form-control selectpicker" name="account_id" data-live-search="true" data-live-search-style="contains" data-size="10">
                  @foreach($lims_account_list as $account)
                  <option value="{{$account->id}}">{{$account->coa_label}}</option>
                  @endforeach
                </select>
              </div>
              <div class="col-md-6 form-group">
                <label> {{trans('file.Type')}}</label>
                <select class="form-control selectpicker" name="type">
                  <option value="0">{{trans('file.All')}}</option>
                  <option value="1">{{trans('file.Debit')}}</option>
                  <option value="2">{{trans('file.Credit')}}</option>
                </select>
              </div>
              <div class="col-md-12 form-group">
                <label>{{trans('file.Choose Your Date')}}</label>
                <div class="input-group">
                  <input type="text" class="account-statement-daterangepicker-field form-control" required />
                  <input type="hidden" name="start_date" />
                  <input type="hidden" name="end_date" />
                </div>
              </div>
            </div>
            <div class="form-group">
              <button type="submit" class="btn btn-primary">{{trans('file.submit')}}</button>
            </div>
            {{ Form::close() }}
          </div>
        </div>
      </div>
    </div>
    <!-- end account statement modal -->

    <!-- warehouse modal -->
    <div id="warehouse-modal" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel" aria-hidden="true" class="modal fade text-left">
      <div role="document" class="modal-dialog">
        <div class="modal-content">
          <div class="modal-header">
            <h5 id="exampleModalLabel" class="modal-title">{{trans('file.Warehouse Report')}}</h5>
            <button type="button" data-dismiss="modal" aria-label="Close" class="close"><span aria-hidden="true"><i class="dripicons-cross"></i></span></button>
          </div>
          <div class="modal-body">
            <p class="italic"><small>{{trans('file.The field labels marked with * are required input fields')}}.</small></p>
            {!! Form::open(['route' => 'report.warehouse', 'method' => 'post']) !!}

            <div class="form-group">
              <label>{{trans('file.Warehouse')}} *</label>
              <select name="warehouse_id" class="selectpicker form-control" required data-live-search="true" id="warehouse-id" data-live-search-style="begins" title="Select warehouse...">
                @foreach($lims_warehouse_list as $warehouse)
                <option value="{{$warehouse->id}}">{{$warehouse->name}}</option>
                @endforeach
              </select>
            </div>

            <input type="hidden" name="start_date" value="{{date('Y-m').'-'.'01'}}" />
            <input type="hidden" name="end_date" value="{{date('Y-m-d')}}" />

            <div class="form-group">
              <button type="submit" class="btn btn-primary">{{trans('file.submit')}}</button>
            </div>
            {{ Form::close() }}
          </div>
        </div>
      </div>
    </div>
    <!-- end warehouse modal -->

    <!-- user modal -->
    <div id="user-modal" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel" aria-hidden="true" class="modal fade text-left">
      <div role="document" class="modal-dialog">
        <div class="modal-content">
          <div class="modal-header">
            <h5 id="exampleModalLabel" class="modal-title">{{trans('file.User Report')}}</h5>
            <button type="button" data-dismiss="modal" aria-label="Close" class="close"><span aria-hidden="true"><i class="dripicons-cross"></i></span></button>
          </div>
          <div class="modal-body">
            <p class="italic"><small>{{trans('file.The field labels marked with * are required input fields')}}.</small></p>
            {!! Form::open(['route' => 'report.user', 'method' => 'post']) !!}
            <?php
            $lims_user_list = DB::table('users')->where('is_active', true)->get();
            ?>
            <div class="form-group">
              <label>{{trans('file.User')}} *</label>
              <select name="user_id" class="selectpicker form-control" required data-live-search="true" id="user-id" data-live-search-style="begins" title="Select user...">
                @foreach($lims_user_list as $user)
                <option value="{{$user->id}}">{{$user->name . ' (' . $user->phone. ')'}}</option>
                @endforeach
              </select>
            </div>

            <input type="hidden" name="start_date" value="{{date('Y-m').'-'.'01'}}" />
            <input type="hidden" name="end_date" value="{{date('Y-m-d')}}" />

            <div class="form-group">
              <button type="submit" class="btn btn-primary">{{trans('file.submit')}}</button>
            </div>
            {{ Form::close() }}
          </div>
        </div>
      </div>
    </div>
    <!-- end user modal -->

    <!-- customer modal -->
    <div id="customer-modal" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel" aria-hidden="true" class="modal fade text-left">
      <div role="document" class="modal-dialog">
        <div class="modal-content">
          <div class="modal-header">
            <h5 id="exampleModalLabel" class="modal-title">{{trans('file.Customer Report')}}</h5>
            <button type="button" data-dismiss="modal" aria-label="Close" class="close"><span aria-hidden="true"><i class="dripicons-cross"></i></span></button>
          </div>
          <div class="modal-body">
            <p class="italic"><small>{{trans('file.The field labels marked with * are required input fields')}}.</small></p>
            {!! Form::open(['route' => 'report.customer', 'method' => 'post']) !!}
            <?php
            $lims_customer_list = DB::table('customers')->where('is_active', true)->get();
            ?>
            <div class="form-group">
              <label>{{trans('file.customer')}} *</label>
              <select name="customer_id" class="selectpicker form-control" required data-live-search="true" id="customer-id" data-live-search-style="begins" title="Select customer...">
                @foreach($lims_customer_list as $customer)
                <option value="{{$customer->id}}">{{$customer->name . ' (' . $customer->phone_number. ')'}}</option>
                @endforeach
              </select>
            </div>

            <input type="hidden" name="start_date" value="{{date('Y-m').'-'.'01'}}" />
            <input type="hidden" name="end_date" value="{{date('Y-m-d')}}" />

            <div class="form-group">
              <button type="submit" class="btn btn-primary">{{trans('file.submit')}}</button>
            </div>
            {{ Form::close() }}
          </div>
        </div>
      </div>
    </div>
    <!-- end customer modal -->

    <!-- customer group modal -->
    <div id="customer-group-modal" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel" aria-hidden="true" class="modal fade text-left">
      <div role="document" class="modal-dialog">
        <div class="modal-content">
          <div class="modal-header">
            <h5 id="exampleModalLabel" class="modal-title">{{trans('file.Customer Group Report')}}</h5>
            <button type="button" data-dismiss="modal" aria-label="Close" class="close"><span aria-hidden="true"><i class="dripicons-cross"></i></span></button>
          </div>
          <div class="modal-body">
            <p class="italic"><small>{{trans('file.The field labels marked with * are required input fields')}}.</small></p>
            {!! Form::open(['route' => 'report.customer_group', 'method' => 'post']) !!}
            <?php
            $lims_customer_group_list = DB::table('customer_groups')->where('is_active', true)->get();
            ?>
            <div class="form-group">
              <label>{{trans('file.Customer Group')}} *</label>
              <select name="customer_group_id" class="selectpicker form-control" required data-live-search="true" id="customer-group-id" data-live-search-style="begins" title="Select customer group...">
                @foreach($lims_customer_group_list as $customer_group)
                <option value="{{$customer_group->id}}">{{$customer_group->name}}</option>
                @endforeach
              </select>
            </div>

            <input type="hidden" name="start_date" value="{{date('Y-m').'-'.'01'}}" />
            <input type="hidden" name="end_date" value="{{date('Y-m-d')}}" />

            <div class="form-group">
              <button type="submit" class="btn btn-primary">{{trans('file.submit')}}</button>
            </div>
            {{ Form::close() }}
          </div>
        </div>
      </div>
    </div>
    <!-- end customer group modal -->

    <!-- supplier modal -->
    <div id="supplier-modal" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel" aria-hidden="true" class="modal fade text-left">
      <div role="document" class="modal-dialog">
        <div class="modal-content">
          <div class="modal-header">
            <h5 id="exampleModalLabel" class="modal-title">{{trans('file.Supplier Report')}}</h5>
            <button type="button" data-dismiss="modal" aria-label="Close" class="close"><span aria-hidden="true"><i class="dripicons-cross"></i></span></button>
          </div>
          <div class="modal-body">
            <p class="italic"><small>{{trans('file.The field labels marked with * are required input fields')}}.</small></p>
            {!! Form::open(['route' => 'report.supplier', 'method' => 'post']) !!}
            <?php
            $lims_supplier_list = DB::table('suppliers')->where('is_active', true)->get();
            ?>
            <div class="form-group">
              <label>{{trans('file.Supplier')}} *</label>
              <select name="supplier_id" class="selectpicker form-control" required data-live-search="true" id="supplier-id" data-live-search-style="begins" title="Select Supplier...">
                @foreach($lims_supplier_list as $supplier)
                <option value="{{$supplier->id}}">{{$supplier->name . ' (' . $supplier->phone_number. ')'}}</option>
                @endforeach
              </select>
            </div>

            <input type="hidden" name="start_date" value="{{date('Y-m').'-'.'01'}}" />
            <input type="hidden" name="end_date" value="{{date('Y-m-d')}}" />

            <div class="form-group">
              <button type="submit" class="btn btn-primary">{{trans('file.submit')}}</button>
            </div>
            {{ Form::close() }}
          </div>
        </div>
      </div>
    </div>
    <!-- end supplier modal -->
  </div>
  @if(!config('database.connections.saleprosaas_landlord'))
  <script type="text/javascript" src="<?php echo asset('vendor/jquery/jquery.min.js') ?>"></script>
  <script type="text/javascript" src="<?php echo asset('vendor/jquery/jquery-ui.min.js') ?>"></script>
  <script type="text/javascript" src="<?php echo asset('vendor/jquery/bootstrap-datepicker.min.js') ?>"></script>
  <script type="text/javascript" src="<?php echo asset('vendor/jquery/jquery.timepicker.min.js') ?>"></script>
  <script type="text/javascript" src="<?php echo asset('vendor/popper.js/umd/popper.min.js') ?>">
  </script>
  <script type="text/javascript" src="<?php echo asset('vendor/bootstrap/js/bootstrap.min.js') ?>"></script>
  <script type="text/javascript" src="<?php echo asset('vendor/bootstrap-toggle/js/bootstrap-toggle.min.js') ?>"></script>
  <script type="text/javascript" src="<?php echo asset('vendor/bootstrap/js/bootstrap-select.min.js') ?>"></script>
  @if(Route::current()->getName() == 'sale.pos')
  <script type="text/javascript" src="<?php echo asset('vendor/keyboard/js/jquery.keyboard.js') ?>"></script>
  <script type="text/javascript" src="<?php echo asset('vendor/keyboard/js/jquery.keyboard.extension-autocomplete.js') ?>"></script>
  @endif
  <script type="text/javascript" src="<?php echo asset('js/grasp_mobile_progress_circle-1.0.0.min.js') ?>"></script>
  <script type="text/javascript" src="<?php echo asset('vendor/jquery.cookie/jquery.cookie.js') ?>">
  </script>
  <script type="text/javascript" src="<?php echo asset('vendor/chart.js/Chart.min.js') ?>"></script>
  <script type="text/javascript" src="<?php echo asset('js/charts-custom.js') ?>"></script>
  <script type="text/javascript" src="<?php echo asset('vendor/jquery-validation/jquery.validate.min.js') ?>"></script>
  <script type="text/javascript" src="<?php echo asset('vendor/malihu-custom-scrollbar-plugin/jquery.mCustomScrollbar.concat.min.js') ?>"></script>
  @if( Config::get('app.locale') == 'ar' || $general_setting->is_rtl)
  <script type="text/javascript" src="<?php echo asset('js/front_rtl.js') ?>"></script>
  @else
  <script type="text/javascript" src="<?php echo asset('js/front.js') ?>"></script>
  @endif

  @if(Route::current()->getName() != '/')
  <script type="text/javascript" src="<?php echo asset('vendor/daterange/js/moment.min.js') ?>"></script>
  <script type="text/javascript" src="<?php echo asset('vendor/daterange/js/knockout-3.4.2.js') ?>"></script>
  <script type="text/javascript" src="<?php echo asset('vendor/daterange/js/daterangepicker.min.js') ?>"></script>
  <script type="text/javascript" src="<?php echo asset('vendor/tinymce/js/tinymce/tinymce.min.js') ?>"></script>
  <script type="text/javascript" src="<?php echo asset('js/dropzone.js') ?>"></script>

  <!-- table sorter js-->
  @if( Config::get('app.locale') == 'ar')
  <script type="text/javascript" src="<?php echo asset('vendor/datatable/pdfmake_arabic.min.js') ?>"></script>
  <script type="text/javascript" src="<?php echo asset('vendor/datatable/vfs_fonts_arabic.js') ?>"></script>
  @else
  <script type="text/javascript" src="<?php echo asset('vendor/datatable/pdfmake.min.js') ?>"></script>
  <script type="text/javascript" src="<?php echo asset('vendor/datatable/vfs_fonts.js') ?>"></script>
  @endif
  <script type="text/javascript" src="<?php echo asset('vendor/datatable/jquery.dataTables.min.js') ?>"></script>
  <script type="text/javascript" src="<?php echo asset('vendor/datatable/dataTables.bootstrap4.min.js') ?>"></script>
  <script type="text/javascript" src="<?php echo asset('vendor/datatable/dataTables.buttons.min.js') ?>"></script>
  <script type="text/javascript" src="<?php echo asset('vendor/datatable/jszip.min.js') ?>"></script>
  <script type="text/javascript" src="<?php echo asset('vendor/datatable/buttons.bootstrap4.min.js') ?>">
    ">
  </script>
  <script type="text/javascript" src="<?php echo asset('vendor/datatable/buttons.colVis.min.js') ?>"></script>
  <script type="text/javascript" src="<?php echo asset('vendor/datatable/buttons.html5.min.js') ?>"></script>
  <script type="text/javascript" src="<?php echo asset('vendor/datatable/buttons.printnew.js') ?>"></script>

  <script type="text/javascript" src="<?php echo asset('vendor/datatable/sum().js') ?>"></script>
  <script type="text/javascript" src="<?php echo asset('vendor/datatable/dataTables.checkboxes.min.js') ?>"></script>
  <script type="text/javascript" src="https://cdn.datatables.net/fixedheader/3.1.6/js/dataTables.fixedHeader.min.js"></script>
  <script type="text/javascript" src="https://cdn.datatables.net/responsive/2.2.3/js/dataTables.responsive.min.js"></script>
  <script type="text/javascript" src="https://cdn.datatables.net/responsive/2.2.3/js/responsive.bootstrap.min.js"></script>
  @endif
  @else
  <script type="text/javascript" src="<?php echo asset('../../vendor/jquery/jquery.min.js') ?>"></script>
  <script type="text/javascript" src="<?php echo asset('../../vendor/jquery/jquery-ui.min.js') ?>"></script>
  <script type="text/javascript" src="<?php echo asset('../../vendor/jquery/bootstrap-datepicker.min.js') ?>"></script>
  <script type="text/javascript" src="<?php echo asset('../../vendor/jquery/jquery.timepicker.min.js') ?>"></script>
  <script type="text/javascript" src="<?php echo asset('../../vendor/popper.js/umd/popper.min.js') ?>">
  </script>
  <script type="text/javascript" src="<?php echo asset('../../vendor/bootstrap/js/bootstrap.min.js') ?>"></script>
  <script type="text/javascript" src="<?php echo asset('../../vendor/bootstrap-toggle/js/bootstrap-toggle.min.js') ?>"></script>
  <script type="text/javascript" src="<?php echo asset('../../vendor/bootstrap/js/bootstrap-select.min.js') ?>"></script>

  <script type="text/javascript" src="<?php echo asset('../../js/grasp_mobile_progress_circle-1.0.0.min.js') ?>"></script>
  <script type="text/javascript" src="<?php echo asset('../../vendor/jquery.cookie/jquery.cookie.js') ?>">
  </script>
  <script type="text/javascript" src="<?php echo asset('../../vendor/chart.js/Chart.min.js') ?>"></script>
  <script type="text/javascript" src="<?php echo asset('../../js/charts-custom.js') ?>"></script>
  <script type="text/javascript" src="<?php echo asset('../../vendor/jquery-validation/jquery.validate.min.js') ?>"></script>
  <script type="text/javascript" src="<?php echo asset('../../vendor/malihu-custom-scrollbar-plugin/jquery.mCustomScrollbar.concat.min.js') ?>"></script>
  @if( Config::get('app.locale') == 'ar' || $general_setting->is_rtl)
  <script type="text/javascript" src="<?php echo asset('../../js/front_rtl.js') ?>"></script>
  @else
  <script type="text/javascript" src="<?php echo asset('../../js/front.js') ?>"></script>
  @endif

  @if(Route::current()->getName() != '/')
  <script type="text/javascript" src="<?php echo asset('../../vendor/daterange/js/moment.min.js') ?>"></script>
  <script type="text/javascript" src="<?php echo asset('../../vendor/daterange/js/knockout-3.4.2.js') ?>"></script>
  <script type="text/javascript" src="<?php echo asset('../../vendor/daterange/js/daterangepicker.min.js') ?>"></script>
  <script type="text/javascript" src="<?php echo asset('../../vendor/tinymce/js/tinymce/tinymce.min.js') ?>"></script>
  <script type="text/javascript" src="<?php echo asset('../../js/dropzone.js') ?>"></script>

  <!-- table sorter js-->
  @if( Config::get('app.locale') == 'ar')
  <script type="text/javascript" src="<?php echo asset('../../vendor/datatable/pdfmake_arabic.min.js') ?>"></script>
  <script type="text/javascript" src="<?php echo asset('../../vendor/datatable/vfs_fonts_arabic.js') ?>"></script>
  @else
  <script type="text/javascript" src="<?php echo asset('../../vendor/datatable/pdfmake.min.js') ?>"></script>
  <script type="text/javascript" src="<?php echo asset('../../vendor/datatable/vfs_fonts.js') ?>"></script>
  @endif
  <script type="text/javascript" src="<?php echo asset('../../vendor/datatable/jquery.dataTables.min.js') ?>"></script>
  <script type="text/javascript" src="<?php echo asset('../../vendor/datatable/dataTables.bootstrap4.min.js') ?>"></script>
  <script type="text/javascript" src="<?php echo asset('../../vendor/datatable/dataTables.buttons.min.js') ?>"></script>
  <script type="text/javascript" src="<?php echo asset('../../vendor/datatable/buttons.bootstrap4.min.js') ?>">
    ">
  </script>
  <script type="text/javascript" src="<?php echo asset('../../vendor/datatable/buttons.colVis.min.js') ?>"></script>
  <script type="text/javascript" src="<?php echo asset('../../vendor/datatable/buttons.html5.min.js') ?>"></script>
  <script type="text/javascript" src="<?php echo asset('../../vendor/datatable/buttons.printnew.js') ?>"></script>

  <script type="text/javascript" src="<?php echo asset('../../vendor/datatable/sum().js') ?>"></script>
  <script type="text/javascript" src="<?php echo asset('../../vendor/datatable/dataTables.checkboxes.min.js') ?>"></script>
  <script type="text/javascript" src="https://cdn.datatables.net/fixedheader/3.1.6/js/dataTables.fixedHeader.min.js"></script>
  <script type="text/javascript" src="https://cdn.datatables.net/responsive/2.2.3/js/dataTables.responsive.min.js"></script>
  <script type="text/javascript" src="https://cdn.datatables.net/responsive/2.2.3/js/responsive.bootstrap.min.js"></script>
  @endif
  @endif
  <script type="text/javascript">
    /*
     * parseLocalizedNumber(): pengganti parseFloat() untuk angka yang sudah
     * diformat lokal.
     *
     * Banyak halaman merender angka dari server dengan number_format(...,',','.')
     * sehingga di DOM tertulis "50.000,00". parseFloat("50.000,00") mengembalikan
     * 50 - bukan 50000 - karena parseFloat berhenti di titik ribuan yang kedua.
     * Akibatnya perhitungan ulang di halaman edit (grand total, subtotal, dll)
     * mengecil 1000x dan nilai salah itu ikut tersimpan ke database.
     *
     * Fungsi ini menangani ketiga bentuk yang bisa muncul di DOM:
     *   "50.000,00" / "1.234.567"  -> titik = pemisah ribuan, koma = desimal
     *   "50000.00"                 -> format mentah dari toFixed()
     *   "Rp 50.000"                -> simbol/spasi diabaikan
     */
    window.parseLocalizedNumber = function(value) {
      if (typeof value === 'number') {
        return isNaN(value) ? 0 : value;
      }
      value = String(value === null || typeof value === 'undefined' ? '' : value)
                .replace(/[^\d,.-]/g, '')
                .trim();
      if (!value) {
        return 0;
      }
      if (value.indexOf(',') !== -1) {
        // Ada koma -> koma pasti desimal, titik pasti ribuan. "50.000,00" -> "50000.00"
        value = value.replace(/\./g, '').replace(',', '.');
      }
      else if ((value.match(/\./g) || []).length > 1 || /^-?\d{1,3}(\.\d{3})+$/.test(value)) {
        // Tanpa koma, tapi polanya ribuan: "1.234.567" atau "50.000" -> buang titik.
        // "50000.00" (satu titik, 2 digit di belakang) tidak kena aturan ini.
        value = value.replace(/\./g, '');
      }
      var parsed = parseFloat(value);
      return isNaN(parsed) ? 0 : parsed;
    };

    /*
     * formatLocalizedNumber(): kebalikan dari parseLocalizedNumber().
     * 1234567.5 -> "1.234.567,50" supaya angka total di footer tabel tampil
     * dengan format yang sama persis dengan angka di barisnya.
     */
    window.formatLocalizedNumber = function(value, decimals) {
      var n = window.parseLocalizedNumber(value);
      decimals = (typeof decimals === 'undefined') ? {{ $general_setting->decimal ?? 2 }} : decimals;
      var negative = n < 0;
      var fixed = Math.abs(n).toFixed(decimals);
      var parts = fixed.split('.');
      parts[0] = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, '.');
      return (negative ? '-' : '') + (parts.length > 1 ? parts[0] + ',' + parts[1] : parts[0]);
    };

    window.normalizeMoneyInput = function(value) {
      var n = window.parseLocalizedNumber(value);
      return n ? n.toString() : '0';
    };

    $(document).on('blur', '.money-display-input', function() {
      var value = $(this).val();
      if (value === '') return;
      $(this).val(window.formatLocalizedNumber(value));
    });

    $(document).on('submit', 'form', function() {
      $(this).find('.money-display-input[name]').each(function() {
        if ($(this).val() !== '') {
          $(this).val(window.normalizeMoneyInput($(this).val()));
        }
      });
    });

    /*
     * Re-register DataTables' sum() plug-in INLINE.
     *
     * vendor/datatable/sum().js sudah diperbaiki, tapi file itu statis dan
     * di-cache keras oleh browser + service worker, jadi di banyak instalasi
     * yang jalan masih versi lama yang memakai
     * `.replace(/[^\d.-]/g,'') * 1`. Versi lama itu membaca "260,00" sebagai
     * 26000 (koma dibuang, bukan dianggap desimal), sehingga total di footer
     * tabel bisa 100x atau 1000x lebih besar dari jumlah barisnya.
     *
     * Karena blok ini di-render server-side di setiap request, ia tidak
     * pernah basi. Ia dijalankan SETELAH sum().js dimuat sehingga
     * registrasi di sini yang menang, dan perbaikannya otomatis berlaku di
     * semua halaman yang memakai .sum() untuk total footer.
     */
    if (window.jQuery && jQuery.fn.dataTable && jQuery.fn.dataTable.Api) {
      jQuery.fn.dataTable.Api.register('sum()', function () {
        return this.flatten().reduce(function (a, b) {
          return window.parseLocalizedNumber(a) + window.parseLocalizedNumber(b);
        }, 0);
      });
    }
  </script>
  @stack('scripts')
  <script>
    if ('serviceWorker' in navigator) {
      window.addEventListener('load', function() {
        navigator.serviceWorker.register('/salepro/service-worker.js').then(function(registration) {
          // Registration was successful
          console.log('ServiceWorker registration successful with scope: ', registration.scope);
        }, function(err) {
          // registration failed :(
          console.log('ServiceWorker registration failed: ', err);
        });
      });
    }
  </script>
  <script type="text/javascript">
    $.ajaxSetup({
      headers: {
        'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
      }
    });
	    var theme = <?php echo json_encode($theme); ?>;
    if (theme == 'dark') {
      $('body').addClass('dark-mode');
      $('#switch-theme i').addClass('dripicons-brightness-low');
    } else {
      $('body').removeClass('dark-mode');
      $('#switch-theme i').addClass('dripicons-brightness-max');
    }
    $('#switch-theme').click(function() {
      if (theme == 'light') {
        theme = 'dark';
        var url = <?php echo json_encode(route('switchTheme', 'dark')); ?>;
        $('body').addClass('dark-mode');
        $('#switch-theme i').addClass('dripicons-brightness-low');
      } else {
        theme = 'light';
        var url = <?php echo json_encode(route('switchTheme', 'light')); ?>;
        $('body').removeClass('dark-mode');
        $('#switch-theme i').addClass('dripicons-brightness-max');
      }

      $.get(url, function(data) {
        console.log('theme changed to ' + theme);
      });
    });

    var alert_product = <?php echo json_encode($alert_product) ?>;

    if ($(window).outerWidth() > 1199) {
      $('nav.side-navbar').removeClass('shrink');
    }

    function myFunction() {
      setTimeout(showPage, 100);
    }

    function showPage() {
      document.getElementById("loader").style.display = "none";
      document.getElementById("content").style.display = "block";
    }

    $("div.alert").delay(4000).slideUp(800);

    function confirmDelete() {
      if (confirm("Are you sure want to delete?")) {
        return true;
      }
      return false;
    }

    $("li#notification-icon").on("click", function(argument) {
      $.get('notifications/mark-as-read', function(data) {
        $("span.notification-number").text(alert_product);
      });
    });

    $("a#add-expense").click(function(e) {
      e.preventDefault();
      $('#expense-modal').modal();
    });

    $("a#send-notification").click(function(e) {
      e.preventDefault();
      $('#notification-modal').modal();
    });

    $("a#account-statement").click(function(e) {
      e.preventDefault();
      $('#account-statement-modal').modal();
    });

    $("a#profitLoss-link").click(function(e) {
      e.preventDefault();
      $("#profitLoss-report-form").submit();
    });

    $("a#report-link").click(function(e) {
      e.preventDefault();
      $("#product-report-form").submit();
    });

    $("a#purchase-report-link").click(function(e) {
      e.preventDefault();
      $("#purchase-report-form").submit();
    });

    $("a#sale-report-link").click(function(e) {
      e.preventDefault();
      $("#sale-report-form").submit();
    });
    $("a#sale-report-chart-link").click(function(e) {
      e.preventDefault();
      $("#sale-report-chart-form").submit();
    });

    $("a#payment-report-link").click(function(e) {
      e.preventDefault();
      $("#payment-report-form").submit();
    });

    $("a#warehouse-report-link").click(function(e) {
      e.preventDefault();
      $('#warehouse-modal').modal();
    });

    $("a#user-report-link").click(function(e) {
      e.preventDefault();
      $('#user-modal').modal();
    });

    $("a#customer-report-link").click(function(e) {
      e.preventDefault();
      $('#customer-modal').modal();
    });

    $("a#customer-group-report-link").click(function(e) {
      e.preventDefault();
      $('#customer-group-modal').modal();
    });

    $("a#supplier-report-link").click(function(e) {
      e.preventDefault();
      $('#supplier-modal').modal();
    });

    $("a#due-report-link").click(function(e) {
      e.preventDefault();
      $("#customer-due-report-form").submit();
    });

    $("a#supplier-due-report-link").click(function(e) {
      e.preventDefault();
      $("#supplier-due-report-form").submit();
    });

    $(".account-statement-daterangepicker-field").daterangepicker({
      callback: function(startDate, endDate, period) {
        var start_date = startDate.format('YYYY-MM-DD');
        var end_date = endDate.format('YYYY-MM-DD');
        var title = start_date + ' To ' + end_date;
        $(this).val(title);
        $('#account-statement-modal input[name="start_date"]').val(start_date);
        $('#account-statement-modal input[name="end_date"]').val(end_date);
      }
    });

    $('.date').datepicker({
      format: "dd-mm-yyyy",
      autoclose: true,
      todayHighlight: true
    });

    $('.selectpicker').selectpicker({
      style: 'btn-link',
    });

    (function() {
      var draftPrefix = 'dekatpos:form-draft:';
      var draftServerUrl = "{{ route('app-drafts.show') }}";
      var draftServerStoreUrl = "{{ route('app-drafts.store') }}";
      var draftServerDeleteUrl = "{{ route('app-drafts.destroy') }}";
      var draftTimers = {};
      var pendingDraftKey = 'dekatpos:pending-form-draft';

      @if(session()->has('message'))
      try {
        var completedDraft = JSON.parse(sessionStorage.getItem(pendingDraftKey) || 'null');
        if (completedDraft && completedDraft.localKey && completedDraft.serverScope) {
          localStorage.removeItem(completedDraft.localKey);
          $.ajax({
            url: draftServerDeleteUrl,
            type: 'DELETE',
            data: { scope: completedDraft.serverScope }
          });
        }
        sessionStorage.removeItem(pendingDraftKey);
      } catch (e) {
        sessionStorage.removeItem(pendingDraftKey);
      }
      @endif

      function applyFormDraft($form, values) {
        if (!values) return;
        var fieldIndexes = {};
        $.each(values, function(i, field) {
          var selector = '[name="' + field.name.replace(/"/g, '\\"') + '"]';
          var $fields = $form.find(selector);
          if (!$fields.length || $fields.first().attr('type') === 'file' || $fields.first().attr('type') === 'password') return;
          if ($fields.first().is('[data-draft-ignore]')) return;
          if ($fields.first().is(':checkbox,:radio')) {
            $fields.filter('[value="' + String(field.value).replace(/"/g, '\\"') + '"]').prop('checked', true);
          }
          else {
            var fieldIndex = fieldIndexes[field.name] || 0;
            var $field = $fields.length > 1 ? $fields.eq(fieldIndex) : $fields;
            $field.val(field.value);
            fieldIndexes[field.name] = fieldIndex + 1;
          }
        });
        if ($.fn.selectpicker) $form.find('.selectpicker').selectpicker('refresh');
      }

      $('form').each(function(index) {
        var $form = $(this);
        var method = String($form.attr('method') || '').toLowerCase();
        var spoofedMethod = String($form.find('input[name="_method"]').first().val() || '').toUpperCase();
        if (method !== 'post' || ['PUT', 'PATCH', 'DELETE'].indexOf(spoofedMethod) !== -1) return;
        var formId = $form.attr('id') || $form.attr('action') || ('form-' + index);
        var draftKey = draftPrefix + location.pathname + ':' + formId;
        var serverScope = 'form:' + location.pathname + ':' + formId;

        try {
          var raw = localStorage.getItem(draftKey);
          if (raw) {
            applyFormDraft($form, JSON.parse(raw));
          }
        } catch (e) {}

        $.get(draftServerUrl, { scope: serverScope }, function(resp) {
          if (resp && resp.draft) {
            applyFormDraft($form, resp.draft);
          }
        });

        $form.on('input change', 'input, select, textarea', function() {
          try {
            var values = $form.serializeArray().filter(function(field) {
              return field.name !== '_token' && field.name !== '_method';
            });
            localStorage.setItem(draftKey, JSON.stringify(values));
            clearTimeout(draftTimers[draftKey]);
            draftTimers[draftKey] = setTimeout(function() {
              $.post(draftServerStoreUrl, {
                scope: serverScope,
                draft: values
              });
            }, 700);
          } catch (e) {}
        });

        $form.on('submit', function(event) {
          if (event.isDefaultPrevented()) return;
          try {
            var values = $form.serializeArray().filter(function(field) {
              return field.name !== '_token' && field.name !== '_method';
            });
            localStorage.setItem(draftKey, JSON.stringify(values));
            sessionStorage.setItem(pendingDraftKey, JSON.stringify({
              localKey: draftKey,
              serverScope: serverScope
            }));
          } catch (e) {}
        });
      });
    })();

	  </script>
</body>

</html>
