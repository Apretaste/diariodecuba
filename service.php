<?php
use Goutte\Client;

class Service
{
	/**
	 * Function executed when the service is called
	 *
	 * @param Request
	 * @param Response
	 */
	public function _main(Request $request, Response &$response)
	{
		$response->setLayout('diariodecuba.ejs');
		$response->setTemplate("allStories.ejs", $this->allStories());
	}

	/**
	 * Call to show the news
	 *
	 * @param Request
	 * @return Response
	 */
	public function _buscar(Request $request)
	{
		// no allow blank entries
		if (empty($request->query))
		{

			$response->setLayout('diariodecuba.ejs');
			$response->createFromText("Su busqueda parece estar en blanco, debe decirnos sobre que tema desea leer");
			}

		// search by the query
		try {
			$articles = $this->searchArticles($request->query);
		} catch (Exception $e) {
			return $this->respondWithError();
		}

		// error if the search returns empty
		if(empty($articles))
		{

			$response->setLayout('diariodecuba.ejs');
			$response->createFromText("Su busqueda <b>{$request->query}</b> no gener&oacute; ning&uacute;n resultado. Por favor cambie los t&eacute;rminos de b&uacute;squeda e intente nuevamente.");
			}

		$responseContent = array(
			"articles" => $articles,
			"search" => $request->query
		);

		$response->setLayout('diariodecuba.ejs');
		$response->setTemplate("searchArticles.ejs", $responseContent);
	}

	/**
	 * Call to show the news
	 *
	 * @param Request
	 * @param Response
	 */
	public function _historia(Request $request, Response &$response)
	{
		// get link to the article
		$link = $request->input->data->link;

		// no allow blank entries
		if (empty($link)) {
			$response->createFromText("Su busqueda parece estar en blanco, debe decirnos que articulo quiere leer");
		}

		// send the actual response
		try {
			$responseContent = $this->story($link);
		} catch (Exception $e) {
			return $this->respondWithError();
		}

		// get the image if exist
		$images = array();
		if( ! empty($responseContent['img'])) {
			$images = array($responseContent['img']);
		}

		$response->setCache();
		$response->setLayout('diariodecuba.ejs');
		$response->setTemplate("story.ejs", $responseContent, $images);
	}

	/**
	 * Call list by categoria
	 *
	 * @param Request
	 * @return Response
	 */
	public function _categoria(Request $request)
	{
		if (empty($request->query))
		{

			$response->setLayout('diariodecuba.ejs');
			$response->createFromText("Su busqueda parece estar en blanco, debe decirnos sobre que categor&iacute;a desea leer");
			}

		$responseContent = array(
			"articles" => $this->listArticles($request->query)["articles"],
			"category" => $request->query
		);

		$response->setLayout('diariodecuba.ejs');
		$response->setTemplate("catArticles.ejs", $responseContent);
	}

	/**
	 * Search stories
	 *
	 * @param String
	 * @return Array
	 */
	private function searchArticles($query)
	{
		// Setup crawler
		$client = new Client();
		$url = "http://www.diariodecuba.com/search/node/".urlencode($query);
		$crawler = $client->request('GET', $url);

		// Collect saearch by term
		$articles = array();
		$crawler->filter('div.search-result')->each(function($item) use (&$articles){

			$articles[] = ["description" => $item->filter('p.search-snippet')->text(),
						   "title"	=> $item->filter('h1.search-title > a')->text(),
						   "link" => str_replace('http://www.diariodecuba.com/',"",$item->filter('h1.search-title > a')->attr("href"))];
						   
		});
		return $articles;
	}

	/**
	 * Get the array of news by content
	 *
	 * @param String
	 * @return Array
	 */
	private function listArticles($query)
	{
		// Setup crawler
		$client = new Client();
		$crawler = $client->request('GET', "http://www.diariodecuba.com/rss.xml");

		// Collect articles by category
		$articles = array();
		$crawler->filter('channel item')->each(function($item, $i) use (&$articles, $query)
		{
			// if category matches, add to list of articles
			$item->filter('category')->each(function($cat, $i) use (&$articles, $query, $item)
			{
				if (strtoupper($cat->text()) == strtoupper($query))
				{
					$title = $item->filter('title')->text();
					$link = $this->urlSplit($item->filter('link')->text());
					$pubDate = $item->filter('pubDate')->text();
					$description = $item->filter('description')->text();
					$cadenaAborrar = "/<!-- google_ad_section_start --><!-- google_ad_section_end --><p>/";
					$description = preg_replace($cadenaAborrar, '', $description);
					$description = preg_replace("/<\/?a[^>]*>/", '', $description);//quitamos las <a></a>
					$description = preg_replace("/<\/?p[^>]*>/", '', $description);//quitamos las <p></p>

					$author = "desconocido";
					if ($item->filter('dc|creator')->count() > 0)
					{
						$authorString = trim($item->filter('dc|creator')->text());
						$author = "({$authorString})";
					}

					$articles[] = array(
						"title" => $title,
						"link" => $link,
						"pubDate" => $pubDate,
						"description" => $description,
						"author" => $author
					);
				}
			});
		});

		// Return response content
		return array("articles" => $articles);
	}

	/**
	 * Get all stories from a query
	 *
	 * @return Array
	 */
	private function allStories()
	{
		// create a new client
		$client = new Client();
		$guzzle = $client->getClient();
		$client->setClient($guzzle);

		// create a crawler
		$crawler = $client->request('GET', "http://www.diariodecuba.com/rss.xml"); 

		$articles = array();
		$crawler->filter('channel item')->each(function($item, $i) use (&$articles)
		{

			// get all parameters
			$title = $item->filter('title')->text();
			$link = $this->urlSplit($item->filter('link')->text());
			$description = $item->filter('description')->text();
			$cadenaAborrar = "/<!-- google_ad_section_start --><!-- google_ad_section_end --><p>/";
			$description = preg_replace($cadenaAborrar, '', $description);
			$description = preg_replace("/<\/?a[^>]*>/", '', $description);//quitamos las <a></a>
			$description = preg_replace("/<\/?p[^>]*>/", '', $description);//quitamos las <p></p>
			$pubDate = $item->filter('pubDate')->text();
			$category = $item->filter('category')->each(function($category, $j) {return $category->text();});

			if ($item->filter('dc|creator')->count() == 0) $author = "desconocido";
			else
			{
				$authorString = trim($item->filter('dc|creator')->text());
				$author = "({$authorString})";
			}

			$categoryLink = array();
			foreach ($category as $currCategory)
			{
				$categoryLink[] = $currCategory;
			}

			$articles[] = array(
				"title" => $title,
				"link" => $link,
				"pubDate" => $pubDate,
				"description" => $description,
				"category" => $category,
				"categoryLink" => $categoryLink,
				"author" => $author
			);
		});

		// return response content
		return array("articles" => $articles);
	}

	/**
	 * Get an specific news to display
	 *
	 * @param String
	 * @return Array
	 */
	private function story($query)
	{
		// create a new client
		$client = new Client();
		$guzzle = $client->getClient();
		$client->setClient($guzzle);

		// create a crawler
		$crawler = $client->request('GET', "http://www.diariodecuba.com/$query");

		// search for title
		$title = $crawler->filter('h1.title')->text();

		// get the intro

		$titleObj = $crawler->filter('div.content:nth-child(1) p:nth-child(1)');
		$intro = $titleObj->count()>0 ? $titleObj->text() : "";

		// get the images
		$imageObj = $crawler->filter('figure.field-field-image-content img');
		$imgUrl = ""; $imgAlt = ""; $img = "";
		if ($imageObj->count() != 0)
		{
			$imgUrl = trim($imageObj->attr("src"));
			$imgAlt = trim($imageObj->attr("alt"));

			// get the image
			if ( ! empty($imgUrl))
			{
				$imgName = $this->utils->generateRandomHash() . "." . pathinfo($imgUrl, PATHINFO_EXTENSION);
				$img = \Phalcon\DI\FactoryDefault::getDefault()->get('path')['root'] . "/temp/$imgName";
				file_put_contents($img, file_get_contents($imgUrl));
			}
		}

		// get the array of paragraphs of the body
		$paragraphs = $crawler->filter('div.node div.content p');
		$content = array();
		foreach ($paragraphs as $p)
		{
			$content[] = trim($p->textContent);
		}

		// create a json object to send to the template
		return array(
			"title" => $title,
			"intro" => $intro,
			"img" => $img,
			"imgAlt" => $imgAlt,
			"content" => $content,
			"url" => "http://www.diariodecuba.com/$query"
		);
	}

	/**
	 * Get the link to the news starting from the /content part
	 *
	 * @param String
	 * @return String
	 * http://www.martinoticias.com/content/blah
	 */
	private function urlSplit($url)
	{
		$url = explode("/", trim($url));
		unset($url[0]);
		unset($url[1]);
		unset($url[2]);
		return implode("/", $url);
	}

	/**
	 * Return a generic error email, usually for try...catch blocks
	 *
	 * @auhor salvipascual
	 * @return Respose
	 */
	private function respondWithError()
	{
		error_log("WARNING: ERROR ON SERVICE DIARIO DE CUBA");

		$response->setLayout('diariodecuba.ejs');
		$response->createFromText("Lo siento pero hemos tenido un error inesperado. Enviamos una peticion para corregirlo. Por favor intente nuevamente mas tarde.");
	}
}
