// JavaScript Document
// v1.0.0
jQuery(function($) {

	// Use strict
	"use strict";
	$(document).ready(function (){

		// header var 1 - classic
		function headerV1Sticky(){
			if (document.body.classList.contains('dtr-apple-sticky-header')) {
				return;
			}
			var windowPos=$(window).scrollTop();
			if( windowPos>350){
				$('.dtr-header-v1 #dtr-header-global').addClass("header-fixed");
				$('.dtr-header-v1 .main-navigation').addClass("dtr-menu-alt").removeClass("dtr-menu-default");
			} else {
				$('.dtr-header-v1 #dtr-header-global').removeClass("header-fixed");
				$('.dtr-header-v1 .main-navigation').addClass("dtr-menu-default").removeClass("dtr-menu-alt");
			}
		}
		headerV1Sticky();
        $(window).on('scroll',headerV1Sticky);

		// header var 2
		function headerV2Sticky(){
			if (document.body.classList.contains('dtr-apple-sticky-header')) {
				return;
			}
			var windowPos=$(window).scrollTop();
			if( windowPos>350){
				$('.dtr-header-v2 #dtr-header-global').addClass("header-fixed");
				$('.dtr-header-v2 .main-navigation').addClass("dtr-menu-alt").removeClass("dtr-menu-default");
			} else {
				$('.dtr-header-v2 #dtr-header-global').removeClass("header-fixed");
				$('.dtr-header-v2 .main-navigation').addClass("dtr-menu-default").removeClass("dtr-menu-alt");
			}
		}
		headerV2Sticky();
		$(window).on('scroll',headerV2Sticky);

		// header var 3 - top fixed
        function headerV3Sticky(){
			if (document.body.classList.contains('dtr-apple-sticky-header')) {
				return;
			}
			var windowPos=$(window).scrollTop();
			if( windowPos>80){
				$('.dtr-header-v3 .fixed-top').addClass("header-fixed");
				$('.dtr-header-v3 .main-navigation').addClass("dtr-menu-alt").removeClass("dtr-menu-default");
			} else {
				$('.dtr-header-v3 .fixed-top').removeClass("header-fixed");
				$('.dtr-header-v3 .main-navigation').addClass("dtr-menu-default").removeClass("dtr-menu-alt");
			}
		}
		headerV3Sticky();
		$(window).on('scroll',headerV3Sticky);

		// header var 4
		function headerV4Sticky(){
			if (document.body.classList.contains('dtr-apple-sticky-header')) {
				return;
			}
			var windowPos=$(window).scrollTop();
			if( windowPos>350){
				$('.dtr-header-v4 #dtr-header-global').addClass("header-fixed");
				$('.dtr-header-v4 .main-navigation').addClass("dtr-menu-alt").removeClass("dtr-menu-default");
			} else {
				$('.dtr-header-v4 #dtr-header-global').removeClass("header-fixed");
				$('.dtr-header-v4 .main-navigation').addClass("dtr-menu-default").removeClass("dtr-menu-alt");
			}
		}
		headerV4Sticky();
        $(window).on('scroll',headerV4Sticky);

        // mobile menu
        $('#dtr-menu-button').on('click', function() {
            $('.slicknav_nav').slideToggle();
            $(this).toggleClass('is-active');
        });

		// one page mods
		// add class to parent of scroll link
		$('.dtr-main-nav a[href^="#"]').parent().addClass('one-page-item');

		// remove wp classes to scroll link
		$('.dtr-main-nav > .one-page-item').removeClass('current-menu-item').removeClass('current_page_item');

		// menu scroll
		var headeroffset = $('#dtr-header-global').height();
		$('.dtr-nav a[href^="#"], .dtr-header-btn[href^="#"], .dtr-scroll-link a[href^="#"]').on('click', function(){
			event.preventDefault();
			var target = this.hash;
			var $target = $(target);
			if($target.length){
				$('html, body').animate({
					scrollTop: $($target).offset().top - headeroffset - 40
				}, 800,'easeOutQuart');
				return false;
			}
		});

		var headeroffset = $('#dtr-header-global').height();
		$('.dtr-scroll-btn').on('click', function(){
			event.preventDefault();
			var target = this.hash;
			var $target = $(target);
			if($target.length){
				$('html, body').animate({
					scrollTop: $($target).offset().top - headeroffset - 60
				}, 800,'easeOutQuart');
				return false;
			}
		});

		// scrollspy
		var win = $(window);
		$("div, section, .section").each(function () {
			if (win.scrollTop() >= $(this).offset().top - 180) {
				$(".dtr-nav li a[href='#" + $(this).attr("id") + "']").addClass("active").parent().siblings().find("a").removeClass("active");
			}
		});
		win.on("scroll", function () {
			$("div, section, .section").each(function () {
				if (win.scrollTop() >= $(this).offset().top - 180) {
					$(".dtr-nav li a[href='#" + $(this).attr("id") + "']").addClass("active").parent().siblings().find("a").removeClass("active");
				}
			});
		});

		// sectionAnchor - link to section from another page
		function sectionAnchor() {
		var navoffset = $('#dtr-header-global').height();
			var hash = window.location.hash;
			if (hash !== '') {
				setTimeout(function() {
					$('html, body').stop().animate({
						scrollTop: $(hash).offset().top - navoffset - 40
					}, 800,'easeOutQuart');
					history.pushState('', document.title, window.location.pathname);
				}, 500);
			}
		} sectionAnchor();

		// responsive header admin bar
		function xstoolbar(){
			var windowPos=$(window).scrollTop();
			if( windowPos>80){
				$('#dtr-responsive-header').addClass("xs-admin-toolbar");
			} else {
				$('#dtr-responsive-header').removeClass("xs-admin-toolbar");
			}
		}
		xstoolbar();
		$(window).scroll(xstoolbar);

		// responsive header nav scroll
		var mnavoffset = $('#dtr-responsive-header').height();
		$('.dtr-responsive-header-menu a[href^="#"]').on('click', function(){
			event.preventDefault();
			var target = this.hash;
			var $target = $(target);
			if($target.length){
				$('html, body').animate({
					scrollTop: $($target).offset().top + mnavoffset - 40
				}, 800,'easeOutQuart');
				return false;
			}
		});

		// scrollspy for responsive
		var smallwin = $(window);
		$("div, section, .section").each(function () {
			if (smallwin.scrollTop() >= $(this).offset().top - 180) {
				$(".slicknav_menu li a[href='#" + $(this).attr("id") + "']").addClass("active").parent().siblings().find("a").removeClass("active");
			}
		});
		smallwin.on("scroll", function () {
			$("div, section, .section").each(function () {
				if (smallwin.scrollTop() >= $(this).offset().top - 180) {
					$(".slicknav_menu a[href='#" + $(this).attr("id") + "']").addClass("active").parent().siblings().find("a").removeClass("active");
				}
			});
		});

		// responsiveAnchor - link to section from another page
		function responsiveAnchor() {
		var windowWidth=$(window).width();
			if(windowWidth<992){
				var mnavoffset = $('#dtr-responsive-header').height();
				var hash = window.location.hash;
				if (hash !== '') {
					setTimeout(function() {
						$('html, body').stop().animate({
							scrollTop: $(hash).offset().top + mnavoffset - 40
						}, 800,'easeOutQuart');
						history.pushState('', document.title, window.location.pathname);
					}, 500);
				}
			}
		} responsiveAnchor();

		// Sroll to top
		var offset = 800;
		var duration = 400;
		$(window).scroll(function() {
		if ($(this).scrollTop() > offset) {
			$('#take-to-top').addClass("active");
		} else {
			$('#take-to-top').removeClass("active");
		}
		});
		$('#take-to-top').on('click', function(event) {
			event.preventDefault();
			$('html, body').animate({scrollTop: 0}, duration);
			return false;
		});

		// menudropdown auto align
		var wapoMainWindowWidth = $(window).width();
		$('.sf-menu ul li').on('mouseover', function(){
			// checks if third level menu exist
			var subMenuExist = $(this).find('.sub-menu').length;
			if( subMenuExist > 0){
				var subMenuWidth = $(this).find('.sub-menu').width();
				var subMenuOffset = $(this).find('.sub-menu').parent().offset().left + subMenuWidth;

				// if sub menu is off screen, give new position
				if((subMenuOffset + subMenuWidth) > wapoMainWindowWidth){
					var newSubMenuPosition = subMenuWidth;
					$(this).find('.sub-menu').css({
						left: -newSubMenuPosition,
						top: '0',
					});
				}
			}
		 }); // menu ends

		 // header search
		$(document).on('click', '.dtr-search-modal-trigger', function () {
			event.preventDefault();
			$('.dtr-search-modal').addClass("open");
		});
		$(document).on('click', function (e) {
			if (e.target.className === 'dtr-search-modal open') {
				$('.dtr-search-modal').removeClass('open');
			}
			if (e.target.className === 'dtr-modal-close') {
				$(e.target).parents(".dtr-search-modal").removeClass('open');
			}
		});

    	// Custom menu widget - accordion style
		function dtrCustomAccordionMenu(){
        	$('.widget_nav_menu .current-menu-ancestor, .elementor-widget-wp-widget-nav_menu .current-menu-ancestor').addClass('active').children('ul').show();
            $('.widget_nav_menu, .elementor-widget-wp-widget-nav_menu').each( function() {
                var $hasChildren = $( this ).find('.menu-item-has-children');
                $hasChildren.each( function() {
                    $( this ).addClass('parent');
                    var $links = $( this ).children('a');
                    $links.on('click', function( event ) {
                        var $linkParent = $( this ).parent('li');
                        var $allParents = $linkParent.parents('li');
                        if ( ! $linkParent.hasClass('active') ) {
                            $hasChildren.not( $allParents ).removeClass('active').children('.sub-menu').slideUp('fast');
                            $linkParent.addClass('active').children('.sub-menu').stop( true, true ).slideDown('fast');
                        } else {
                            $linkParent.removeClass('active').children('.sub-menu').stop( true, true ).slideUp('fast');
                        }
                        return false;
                    } );
                } );
            } );
        }
		dtrCustomAccordionMenu();

		// theme cursor
		function themeCursor(){
			var customCursor	= $('.dtr-cursor-wrapper');
			if(customCursor.length){
				if ($("body")) {
					const e = document.querySelector(".dtr-cursor"),
						t = document.querySelector(".dtr-cursor");
						let n, i = 0,
						o = !1;
					window.onmousemove = function (s) {
						o || (t.style.transform = "translate(" + s.clientX + "px, " + s.clientY + "px)"), e.style.transform = "translate(" + s.clientX + "px, " + s.clientY + "px)", n = s.clientY, i = s.clientX;
					}, $("body").on("mouseenter", "a, button, [type=submit], [type=button], .dtr-pointer", function () {
							e.classList.add("dtr-cursor-hover"), t.classList.add("dtr-cursor-hover");
					}), $("body").on("mouseleave", "a, button, [type=submit], [type=button], .dtr-pointer", function () {
							$(this).is("a") && $(this).closest(".dtr-pointer").length || (e.classList.remove("dtr-cursor-hover"), t.classList.remove("dtr-cursor-hover"));
					}), e.style.visibility = "visible", t.style.visibility = "visible";
				}
			}
		}
		themeCursor();

	}); // ready

	// grid on load
    $(window).on('load', function(){

		// Masonry - post grid
		var $container_blogposts_masonry = $('.dtr-blog-grid.dtr-blog-grid-masonry .dtr-post-grid');
		$container_blogposts_masonry.imagesLoaded(function () {
			$container_blogposts_masonry.isotope({
				itemSelector: '.dtr-blog-grid.dtr-blog-grid-masonry .dtr-post-item',
				// layoutMode : 'fitRows',
				masonry: {columnWidth: '.dtr-blog-grid.dtr-blog-grid-masonry .dtr-post-item'  }
			});
		});

		// Default Post grid
		var $container_blogposts = $('.dtr-blog-grid.dtr-blog-grid-fitrows .dtr-post-grid');
		$container_blogposts.imagesLoaded(function () {
			$container_blogposts.isotope({
				itemSelector: '.dtr-blog-grid.dtr-blog-grid-fitrows .dtr-post-item',
				layoutMode : 'fitRows',
				//masonry: {columnWidth: '.dtr-blog-grid.dtr-post-grid-fitrows .dtr-post-item'  }
			});
		});

		// Masonry - search grid
		var $container_search = $('.dtr-search-grid');
		$container_search.imagesLoaded(function () {
			$container_search.isotope({
				itemSelector: '.dtr-search-grid .dtr-search-item',
				// layoutMode : 'fitRows',
				masonry: {columnWidth: '.dtr-search-grid .dtr-search-item'  }
			});
		});

	}); // Close on load
});