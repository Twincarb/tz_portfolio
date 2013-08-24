<?php
/*------------------------------------------------------------------------

# TZ Portfolio Extension

# ------------------------------------------------------------------------

# author    DuongTVTemPlaza

# copyright Copyright (C) 2012 templaza.com. All Rights Reserved.

# @license - http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL

# Websites: http://www.templaza.com

# Technical Support:  Forum - http://templaza.com/Forum

-------------------------------------------------------------------------*/

// Check to ensure this file is included in Joomla!
defined('_JEXEC') or die;

jimport('joomla.application.component.view');
require_once(JPATH_SITE.'/components/com_tz_portfolio/helpers/article.php');

/**
 * HTML View class for the Content component.
 */
class TZ_PortfolioViewCategory extends JViewLegacy
{
	protected $state;
	protected $items;
	protected $category;
	protected $children;
	protected $pagination;

	protected $lead_items = array();
	protected $intro_items = array();
	protected $link_items = array();
	protected $columns = 1;

	function display($tpl = null)
	{

		$app	= JFactory::getApplication();
		$user	= JFactory::getUser();
        $doc    = JFactory::getDocument();

		// Get some data from the models
        $state		= $this->get('State');
        $params		= $state->params;

        $csscompress    = null;
        if($params -> get('css_compression',0)){
            $csscompress    = '.min';
        }

        $jscompress         = new stdClass();
        $jscompress -> extfile  = null;
        $jscompress -> folder   = null;
        if($params -> get('js_compression',1)){
            $jscompress -> extfile  = '.min';
            $jscompress -> folder   = '/packed';
        }

		$items		= $this->get('Items');
		$category	= $this->get('Category');
		$children	= $this->get('Children');
		$parent		= $this->get('Parent');
		$pagination = $this->get('Pagination');

        if(!COM_TZ_PORTFOLIO_JVERSION_COMPARE){
            $pagination -> pagesTotal   = $pagination -> getPagesCounter();
        }

		// Check for errors.
		if (count($errors = $this->get('Errors'))) {
			JError::raiseError(500, implode("\n", $errors));
			return false;
		}

		if ($category == false) {
			return JError::raiseError(404, JText::_('JGLOBAL_CATEGORY_NOT_FOUND'));
		}

		if ($parent == false) {
			return JError::raiseError(404, JText::_('JGLOBAL_CATEGORY_NOT_FOUND'));
		}

		// Setup the category parameters.
		$cparams = $category->getParams();
		$category->params = clone($params);
		$category->params->merge($cparams);

		// Check whether category access level allows access.
		$user	= JFactory::getUser();
		$groups	= $user->getAuthorisedViewLevels();
		if (!in_array($category->access, $groups)) {
			return JError::raiseError(403, JText::_('JERROR_ALERTNOAUTHOR'));
		}

		// PREPARE THE DATA
		// Get the metrics for the structural page layout.
		$numLeading	= $params->def('num_leading_articles', 1);
		$numIntro	= $params->def('num_intro_articles', 4);
		$numLinks	= $params->def('num_links', 4);

        //Get Plugins Model
        $pmodel = JModelLegacy::getInstance('Plugins','TZ_PortfolioModel',array('ignore_request' => true));

        if($params -> get('comment_function_type','default') != 'js'){
            // Compute the article slugs and prepare introtext (runs content plugins).
            if($params -> get('tz_show_count_comment',1) == 1){
                require_once(JPATH_COMPONENT_ADMINISTRATOR.DIRECTORY_SEPARATOR.'libraries'.DIRECTORY_SEPARATOR.'HTTPFetcher.php');
                require_once(JPATH_COMPONENT_ADMINISTRATOR.DIRECTORY_SEPARATOR.'libraries'.DIRECTORY_SEPARATOR.'readfile.php');
                $fetch       = new Services_Yadis_PlainHTTPFetcher();
            }
            $threadLink = null;
            $comments   = null;
            if($items){
                foreach($items as $key => $item){

                    $slug   = $item->alias ? ($item->id . ':' . $item->alias) : $item->id;

                    $tzRedirect = $params -> get('tz_portfolio_redirect','p_article'); //Set params for $tzRedirect
                    $itemParams = new JRegistry($item -> attribs); //Get Article's Params

                    //Check redirect to view article
                    if($itemParams -> get('tz_portfolio_redirect')){
                        $tzRedirect = $itemParams -> get('tz_portfolio_redirect');
                    }

                    if($tzRedirect == 'p_article'){
                        $contentUrl =JRoute::_(TZ_PortfolioHelperRoute::getPortfolioArticleRoute($slug,$item -> catid), true ,-1);
                    }
                    else{
                        $contentUrl =JRoute::_(TZ_PortfolioHelperRoute::getArticleRoute($slug,$item -> catid), true ,-1);
                    }

                    if($params -> get('tz_show_count_comment',1) == 1){
                        if($params -> get('tz_comment_type','disqus') == 'disqus'){
                            $threadLink .= '&thread[]=link:'.$contentUrl;
                        }elseif($params -> get('tz_comment_type','disqus') == 'facebook'){
                            $threadLink .= '&urls[]='.$contentUrl;
                        }
                    }
                }
            }

            // Get comment counts for all items(articles)
            if($params -> get('tz_show_count_comment',1) == 1){
                // From Disqus
                if($params -> get('tz_comment_type','disqus') == 'disqus'){
                    if($threadLink){
                        $url        = 'https://disqus.com/api/3.0/threads/list.json?api_secret='
                                      .$params -> get('disqusApiSecretKey','4sLbLjSq7ZCYtlMkfsG7SS5muVp7DsGgwedJL5gRsfUuXIt6AX5h6Ae6PnNREMiB')
                                      .'&forum='.$params -> get('disqusSubDomain','templazatoturials')
                                      .$threadLink.'&include=open';

                        $content    = $fetch -> get($url);

                        if($content){
                            if($body    = json_decode($content -> body)){
                                if($responses = $body -> response){
                                    foreach($responses as $response){
                                        $comments[$response ->link]   = $response -> posts;
                                    }
                                }
                            }
                        }
                    }
                }

                // From Facebook
                if($params -> get('tz_comment_type','disqus') == 'facebook'){
                    if($threadLink){
                        $url        = 'http://api.facebook.com/restserver.php?method=links.getStats'
                                      .$threadLink;
                        $content    = $fetch -> get($url);

                        if($content){
                            if($bodies = $content -> body){
                                if(preg_match_all('/\<link_stat\>(.*?)\<\/link_stat\>/ims',$bodies,$matches)){
                                    if(isset($matches[1]) && !empty($matches[1])){
                                        foreach($matches[1]as $val){
                                            $match  = null;
                                            if(preg_match('/\<url\>(.*?)\<\/url\>.*?\<comment_count\>(.*?)\<\/comment_count\>/msi',$val,$match)){
                                                if(isset($match[1]) && isset($match[2])){
                                                    $comments[$match[1]]    = $match[2];
                                                }
                                            }
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
            }
            // End Get comment counts for all items(articles)
        }else{
            // Add facebook script api
            if($params -> get('tz_show_count_comment',1) == 1){
                if($params -> get('tz_comment_type','disqus') == 'facebook'){
                    $doc -> addScriptDeclaration('
                        (function(d, s, id) {
                          var js, fjs = d.getElementsByTagName(s)[0];
                          if (d.getElementById(id)) return;
                          js = d.createElement(s); js.id = id;
                          js.src = "//connect.facebook.net/en_GB/all.js#xfbml=1";
                          fjs.parentNode.insertBefore(js, fjs);
                        }(document, \'script\', \'facebook-jssdk\'));
                   ');
                }

                // Add disqus script api
                if($params -> get('tz_comment_type','disqus') == 'disqus'){
                    $doc -> addScriptDeclaration('
                        /* * * CONFIGURATION VARIABLES: EDIT BEFORE PASTING INTO YOUR WEBPAGE * * */
                        var disqus_shortname = \'templazatoturials\'; // required: replace example with your forum shortname

                        /* * * DON\'T EDIT BELOW THIS LINE * * */
                        (function () {
                        var s = document.createElement(\'script\'); s.async = true;
                        s.type = \'text/javascript\';
                        s.src = \'http://\' + disqus_shortname + \'.disqus.com/count.js\';
                        (document.getElementsByTagName(\'HEAD\')[0] || document.getElementsByTagName(\'BODY\')[0]).appendChild(s);
                        }());
                   ');
                    $doc -> addCustomTag('
                    <script type="text/javascript">
                        window.addEvent("load",function(){
                            var a=document.getElementsByTagName("A");

                            for(var h=0;h<a.length;h++){
                                if(a[h].href.indexOf("#disqus_thread")>=0){
                                var span = document.createElement("span");
                                span.innerHTML  = a[h].innerHTML;
                                a[h].parentNode.appendChild(span);
                                a[h].remove();
                                }
                            }
                        });
                    </script>
                   ');
                }
            }
        }

		for ($i = 0, $n = count($items); $i < $n; $i++)
		{
			$item = &$items[$i];

			$item->slug = $item->alias ? ($item->id . ':' . $item->alias) : $item->id;

            $tzRedirect = $params -> get('tz_portfolio_redirect','p_article'); //Set params for $tzRedirect
            $itemParams = new JRegistry($item -> attribs); //Get Article's Params

            if($params -> get('comment_function_type','default') != 'js'){
                //Check redirect to view article
                if($itemParams -> get('tz_portfolio_redirect')){
                    $tzRedirect = $itemParams -> get('tz_portfolio_redirect');
                }

                if($tzRedirect == 'p_article'){
                    $contentUrl =JRoute::_(TZ_PortfolioHelperRoute::getPortfolioArticleRoute($item -> slug,$item -> catid), true ,-1);
                }
                else{
                    $contentUrl =JRoute::_(TZ_PortfolioHelperRoute::getArticleRoute($item -> slug,$item -> catid), true ,-1);
                }

                if($params -> get('tz_show_count_comment',1) == 1){
                    if($params -> get('tz_comment_type','disqus') == 'disqus' ||
                        $params -> get('tz_comment_type','disqus') == 'facebook'){
                        if($comments){
                            if(array_key_exists($contentUrl,$comments)){
                                $item -> commentCount   = $comments[$contentUrl];
                            }else{
                                $item -> commentCount   = 0;
                            }
                        }else{
                            $item -> commentCount   = 0;
                        }

                    }
                }
            }else{
                $item -> commentCount   = 0;
            }

			// No link for ROOT category
			if ($item->parent_alias == 'root') {
				$item->parent_slug = null;
			}

			$item->event = new stdClass();

			$dispatcher = JDispatcher::getInstance();

            //Get plugin Params for this article
            $pmodel -> setState('filter.contentid',$item -> id);
            $pluginItems    = $pmodel -> getItems();
            $pluginParams   = $pmodel -> getParams();
            $item -> pluginparams   = clone($pluginParams);

			// Ignore content plugins on links.
			if ($i < $numLeading + $numIntro) {
				$item->introtext = JHtml::_('content.prepare', $item->introtext, '', 'com_tz_portfolio.category');

				$results = $dispatcher->trigger('onContentAfterTitle', array('com_tz_portfolio.category', &$item, &$item->params, 0));
				$item->event->afterDisplayTitle = trim(implode("\n", $results));

                $results = $dispatcher->trigger('onContentBeforeDisplay', array('com_tz_portfolio.category', &$item, &$item->params, 0));
			    $item->event->beforeDisplayContent = trim(implode("\n", $results));

				$results = $dispatcher->trigger('onContentAfterDisplay', array('com_tz_portfolio.category', &$item, &$item->params, 0));
				$item->event->afterDisplayContent = trim(implode("\n", $results));

                $results = $dispatcher->trigger('onContentTZPortfolioVote', array('com_tz_portfolio.category', &$item, &$item->params, 0));
				$item->event->TZPortfolioVote = trim(implode("\n", $results));


                //Call trigger in group tz_portfolio
                JPluginHelper::importPlugin('tz_portfolio');

                $item->introtext = JHtml::_('article.tzprepare', $item->introtext, '',$pluginParams, 'com_tz_portfolio.category');

                $results = $dispatcher->trigger('onTZPluginAfterTitle', array('com_tz_portfolio.article', &$item, &$params,&$pluginParams, 0));
                $item->event->TZafterDisplayTitle = trim(implode("\n", $results));

                $results = $dispatcher->trigger('onTZPluginBeforeDisplay', array('com_tz_portfolio.article', &$item, &$params,&$pluginParams, 0));
                $item->event->TZbeforeDisplayContent = trim(implode("\n", $results));

                $results = $dispatcher->trigger('onTZPluginAfterDisplay', array('com_tz_portfolio.article', &$item, &$params,&$pluginParams, 0));
                $item->event->TZafterDisplayContent = trim(implode("\n", $results));

			}
		}

		// Check for layout override only if this is not the active menu item
		// If it is the active menu item, then the view and category id will match
		$active	= $app->getMenu()->getActive();
		if ((!$active) || ((strpos($active->link, 'view=category') === false) || (strpos($active->link, '&id=' . (string) $category->id) === false))) {
			// Get the layout from the merged category params
			if ($layout = $category->params->get('category_layout')) {
				$this->setLayout($layout);
			}
		}
		// At this point, we are in a menu item, so we don't override the layout
		elseif (isset($active->query['layout'])) {
			// We need to set the layout from the query in case this is an alternative menu item (with an alternative layout)
			$this->setLayout($active->query['layout']);
		}

		// For blog layouts, preprocess the breakdown of leading, intro and linked articles.
		// This makes it much easier for the designer to just interrogate the arrays.
		if (($params->get('layout_type') == 'blog') || ($this->getLayout() == 'blog')) {
			$max = count($items);

			// The first group is the leading articles.
			$limit = $numLeading;
			for ($i = 0; $i < $limit && $i < $max; $i++) {
				$this->lead_items[$i] = &$items[$i];
			}

			// The second group is the intro articles.
			$limit = $numLeading + $numIntro;
			// Order articles across, then down (or single column mode)
			for ($i = $numLeading; $i < $limit && $i < $max; $i++) {
				$this->intro_items[$i] = &$items[$i];
			}

			$this->columns = max(1, $params->def('num_columns', 1));
			$order = $params->def('multi_column_order', 1);

			if ($order == 0 && $this->columns > 1) {
				// call order down helper
				$this->intro_items = TZ_PortfolioHelperQuery::orderDownColumns($this->intro_items, $this->columns);
			}

			$limit = $numLeading + $numIntro + $numLinks;
			// The remainder are the links.
			for ($i = $numLeading + $numIntro; $i < $limit && $i < $max;$i++)
			{
					$this->link_items[$i] = &$items[$i];
			}
		}

		$children = array($category->id => $children);

		//Escape strings for HTML output
		$this->pageclass_sfx = htmlspecialchars($params->get('pageclass_sfx'));

		$this->assign('maxLevel', $params->get('maxLevel', -1));
		$this->assignRef('state', $state);
		$this->assignRef('items', $items);
		$this->assignRef('category', $category);
		$this->assignRef('children', $children);
		$this->assignRef('params', $params);
		$this->assignRef('parent', $parent);
		$this->assignRef('pagination', $pagination);
		$this->assignRef('user', $user);
        $this -> assign('listImage',$this -> get('CatImages'));

        $model  = JModelLegacy::getInstance('Portfolio','TZ_PortfolioModel',array('ignore_request' => true));
        $pParams    = clone($params);
        $pParams -> set('tz_catid',array($category -> id));
        $model -> setState('params',$pParams);
        $this -> assign('char',$state -> get('char'));
        $this -> assign('availLetter',$model -> getAvailableLetter());

        $catParams  = $category -> params;
        $params -> merge($catParams);

        $this -> assign('mediaParams',$params);

        if($params -> get('tz_use_image_hover',1) == 1):
            $doc -> addStyleDeclaration('
                .tz_image_hover{
                    opacity: 0;
                    position: absolute;
                    top:0;
                    left: 0;
                    transition: opacity '.$params -> get('tz_image_timeout',0.35).'s ease-in-out;
                   -moz-transition: opacity '.$params -> get('tz_image_timeout',0.35).'s ease-in-out;
                   -webkit-transition: opacity '.$params -> get('tz_image_timeout',0.35).'s ease-in-out;
                }
                .tz_image_hover:hover{
                    opacity: 1;
                    margin: 0;
                }
            ');
        endif;

        if($params -> get('tz_use_lightbox',1) == 1){
            $doc -> addCustomTag('<script type="text/javascript" src="components/com_tz_portfolio/js'.
                $jscompress -> folder.'/jquery.fancybox.pack'.$jscompress -> extfile.'.js"></script>');
            $doc -> addStyleSheet('components/com_tz_portfolio/css/fancybox'.$csscompress.'.css');

            $width      = null;
            $height     = null;
            $autosize   = null;
            if($params -> get('tz_lightbox_width')){
                if(preg_match('/%|px/',$params -> get('tz_lightbox_width'))){
                    $width  = 'width:\''.$params -> get('tz_lightbox_width').'\',';
                }
                else
                    $width  = 'width:'.$params -> get('tz_lightbox_width').',';
            }
            if($params -> get('tz_lightbox_height')){
                if(preg_match('/%|px/',$params -> get('tz_lightbox_height'))){
                    $height  = 'height:\''.$params -> get('tz_lightbox_height').'\',';
                }
                else
                    $height  = 'height:'.$params -> get('tz_lightbox_height').',';
            }
            if($width || $height){
                $autosize   = 'fitToView: false,autoSize: false,';
            }
            $doc -> addCustomTag('<script type="text/javascript">
                jQuery(\'.fancybox\').fancybox({
                    type:\'iframe\',
                    openSpeed:'.$params -> get('tz_lightbox_speed',350).',
                    openEffect: "'.$params -> get('tz_lightbox_transition','elastic').'",
                    '.$width.$height.$autosize.'
		            helpers:  {
                        title : {
                            type : "inside"
                        },
                        overlay : {
                            opacity:'.$params -> get('tz_lightbox_opacity',0.75).',
                        }
                    }
                });
                </script>
            ');
        }

        $doc -> addStyleSheet('components/com_tz_portfolio/css/tzportfolio'.$csscompress.'.css');

		$this->_prepareDocument();

		parent::display($tpl);
	}

	/**
	 * Prepares the document
	 */
	protected function _prepareDocument()
	{
		$app		= JFactory::getApplication();
		$menus		= $app->getMenu();
		$pathway	= $app->getPathway();
		$title		= null;

		// Because the application sets a default page title,
		// we need to get it from the menu item itself
		$menu = $menus->getActive();

		if ($menu) {
			$this->params->def('page_heading', $this->params->get('page_title', $menu->title));
		}
		else {
			$this->params->def('page_heading', JText::_('JGLOBAL_ARTICLES'));
		}

		$id = (int) @$menu->query['id'];

		if ($menu && ($menu->query['option'] != 'com_tz_portfolio' || $menu->query['view'] == 'article' || $id != $this->category->id)) {
			$path = array(array('title' => $this->category->title, 'link' => ''));
			$category = $this->category->getParent();

			while (($menu->query['option'] != 'com_tz_portfolio' || $menu->query['view'] == 'article' || $id != $category->id) && $category->id > 1)
			{
				$path[] = array('title' => $category->title, 'link' => TZ_PortfolioHelperRoute::getCategoryRoute($category->id));
				$category = $category->getParent();
			}

			$path = array_reverse($path);

			foreach ($path as $item)
			{
				$pathway->addItem($item['title'], $item['link']);
			}
		}

		$title = $this->params->get('page_title', '');

		if (empty($title)) {
			$title = $app->getCfg('sitename');
		}
		elseif ($app->getCfg('sitename_pagetitles', 0) == 1) {
			$title = JText::sprintf('JPAGETITLE', $app->getCfg('sitename'), $title);
		}
		elseif ($app->getCfg('sitename_pagetitles', 0) == 2) {
			$title = JText::sprintf('JPAGETITLE', $title, $app->getCfg('sitename'));
		}

		$this->document->setTitle($title);

		if ($this->category->metadesc)
		{
			$this->document->setDescription($this->category->metadesc);
		}
		elseif (!$this->category->metadesc && $this->params->get('menu-meta_description'))
		{
			$this->document->setDescription($this->params->get('menu-meta_description'));
		}

		if ($this->category->metakey)
		{
			$this->document->setMetadata('keywords', $this->category->metakey);
		}
		elseif (!$this->category->metakey && $this->params->get('menu-meta_keywords'))
		{
			$this->document->setMetadata('keywords', $this->params->get('menu-meta_keywords'));
		}

		if ($this->params->get('robots'))
		{
			$this->document->setMetadata('robots', $this->params->get('robots'));
		}

		if ($app->getCfg('MetaAuthor') == '1') {
			$this->document->setMetaData('author', $this->category->getMetadata()->get('author'));
		}

		$mdata = $this->category->getMetadata()->toArray();

		foreach ($mdata as $k => $v)
		{
			if ($v) {
				$this->document->setMetadata($k, $v);
			}
		}

		// Add feed links
		if ($this->params->get('show_feed_link', 1)) {
			$link = '&format=feed&limitstart=';
			$attribs = array('type' => 'application/rss+xml', 'title' => 'RSS 2.0');
			$this->document->addHeadLink(JRoute::_($link . '&type=rss'), 'alternate', 'rel', $attribs);
			$attribs = array('type' => 'application/atom+xml', 'title' => 'Atom 1.0');
			$this->document->addHeadLink(JRoute::_($link . '&type=atom'), 'alternate', 'rel', $attribs);
		}
	}

    protected function FindUserItemId($_userid=null){
        $app		= JFactory::getApplication();
        $menus		= $app->getMenu('site');
        $active     = $menus->getActive();
        if($_userid){
            $userid    = intval($_userid);
        }

        $component	= JComponentHelper::getComponent('com_tz_portfolio');
        $items		= $menus->getItems('component_id', $component->id);

        if($this -> params -> get('user_menu_active') && $this -> params -> get('user_menu_active') != 'auto'){
            return $this -> params -> get('user_menu_active');
        }

        foreach ($items as $item)
        {
            if (isset($item->query) && isset($item->query['view'])) {
                $view = $item->query['view'];

                if (isset($item -> query['created_by'])) {
                    if ($item->query['created_by'] == $userid) {
                        return $item -> id;
                    }
                }
                else{
                    if($item -> home == 1){
                        $homeId = $item -> id;
                    }
                }
            }
        }

        if(!isset($active -> id)){
            return $homeId;
        }

        return $active -> id;
    }
}
