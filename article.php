<?php

namespace App\Sequoia\Resources;

use App\Helpers\Assets\ImageHelper;
use App\Helpers\Urls\SequoiaUrlHelper;
use App\Sequoia\Requests\SolrMoreLikeThisRequest;
use DOMDocument;
use Illuminate\Http\Resources\Json\Resource;
use Illuminate\Support\Carbon;

class ArticleContentResource extends Resource
{
    const relatedArticlesLimit = 5;
    //todo: needs to improve legacy code
    public $_iMaxQuality        = 100;
    public $_iImageQuality      = 85;
    public $_strHeroImageSize    = '660x300';
    public $_strFeatImageSize   = '300x202';
    public $_strThumbImgSize     = '69x46';
    public $_strPhotoSize        = '65x65';
    public $_strSmallThumbImageSize = '36x36';
    public $_strRelatedImageSize    = '201x134';
    public $_strProfilePhoto        = '110x110';
    public $_strPopArtImageSize     = '80x80';
    public $_strStructureDataImage="700x319";
    public $_iContentImageMinWidth=600;
    public $_iContentImageWidth=660;
    public $_iContentImageHeight=300;
    public $_iMobileContentImageWidth=414;
    public $categoryTargets = [];
    public $categoryLevelType = ['category', 'sub-category'];

    protected $articleUrl = null;
    protected $articleImageUrl = null;
    protected $videoTagName;
    protected $videoId;
    protected $_iStaffWriterId=23814;

    const relatedArticleWidgetGACategory='Popular Article Widget';
    const relatedArticleWidgetGALabel='Popular Article Widget - Item ';

    /**
     * Transform the resource obj into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function toArray($request)
    {
        return [
            'articleTitle' => $this->resource['article']['title'],
            'articleUrl' => $this->getArticleUrl(),
            'articleId' => $this->resource['article']['article_id'],
            'articlePageId' => $this->resource['article']['page_id'],
            'articleMetaTitle' => !empty($this->resource['article']['meta_title']) ?
                $this->resource['article']['meta_title'] : '',
            'articleMetaDescription' => !empty($this->resource['article']['meta_description']) ?
                $this->resource['article']['meta_description'] : '',
            'articleContent' => $this->getArticleContent(),
            'articleEstimatedCost'=>$this->getEstimatedCost(),
            'articleTotalTime'=>$this->getTotalTime(),
            'articleLevelDifficulty'=>$this->getLevelDifficulty(),
            'articleAuthor' => $this->getAuthorInfo(),
            'articleReviewer' => $this->getReviewerInfo(),
            'articleTags' => !empty($this->resource['article']['tags']['content']) ? $this->getTagList():[],
            'articleBreadcrumbs'=>$this->getArticleBreadcrumbList(),
            'articleImageUrl'=> !empty($this->resource['article']['content']['lead_image']) ?
                $this->getArticleImageUrl() : "",
            'articleResponsiveImageUrl'=> !empty($this->resource['article']['content']['lead_image']) ?
                ImageHelper::getResizedImageUrl($this->getLeadImageUrl(), '414x188', 100 ,1) : "",
            //todo: check if meta data for published_time required timezone difference
            'articleCreated'=>Carbon::parse($this->resource['article']['created'])->format('Y-m-d'),
            'articleModified'=> Carbon::parse($this->resource['article']['modified'])->format('Y-m-d'),
            'recentArticles' => $this->resource['recent_articles'],
            'relatedArticles' => $this->getRelatedArticles(),
            'contentGroups' => $this->getContentGroups(),
            'steps' => !empty($this->resource['steps']) ? $this->getPrevNextSteps() : [],
            'slideshow'=> $this->resource['article']['content_type']['name'] == 'Slideshow'?
                $this->getSlideShowSlides() : [],
            'toolsAndMaterials' => !empty($this->resource['article']['content']['tools_and_materials']['value']) ?
                $this->getToolsAndMaterials() : '',
            'articleStructureData'=> $this->getStructureData(),
            'hasModernizeScript'=>$this->hasModernizeScript(),
            'categoryTargets'=>$this->categoryTargets,
            'categories'=>$this->getCategoriesIds(),
        ];
    }

    /** Return the categories id for this article
     * @return array
     */
    protected function getCategoriesIds(){
        return collect(!empty($this->resource['article']['breadcrumbs']['primary'])?$this->resource['article']['breadcrumbs']['primary']
            :$this->resource['article']['breadcrumbs'])
            ->map(function($category){
                return $category['category_id'];
            })->toArray();
    }
    /**
     * Returns the author byline information
     * @todo Need to add link to staff bio when author information is not
     *       provided.
     * @return array
     */
    protected function getAuthorInfo(){
        if(!empty($this->resource['article']['author'])){
            $this->resource['article']['author']['photo']=!empty($this->resource['article']['author']['photo'])?
                ImageHelper::getResizedImageUrl($this->resource['article']['author']['photo'],'35x35',100,1)
                : url('/images/DIY-logo-transparent.svg');
            $this->resource['article']['author']['url']=SequoiaUrlHelper::replace( $this->resource['article']['author']['url']);
            $this->resource['article']['author']['bio']=htmlspecialchars(strip_tags($this->resource['article']['author']['bio']));
            $this->resource['article']['author']['hideToolTip']=$this->resource['article']['author']['author_id']==$this->_iStaffWriterId;

            return $this->resource['article']['author'];
        }else{
            return [
                'name'=>'Doityourself Staff',
                'photo'=> url('images/DIY-logo-transparent.svg'),
                'url'=>url('authors/doityourself-staff'),
                'hideToolTip'=>true
            ];
        }
    }

    protected function getReviewerInfo(){
        return !empty($this->resource['article']['coauthor']) &&
        ($this->resource['article']['coauthor']['byline_label']=="Reviewed By")?
            $this->setReviewerInfo($this->resource['article']['coauthor']):[];
    }

    protected function setReviewerInfo($reviewer){
        $reviewer['url']=SequoiaUrlHelper::replace($reviewer['url']);
        return $reviewer;
    }

    protected function setArticleUrl(){
        $this->articleUrl = SequoiaUrlHelper::replace($this->resource['article']['url']);
    }

    public function getArticleUrl(){

        if(empty($this->articleUrl)){
            $this->setArticleUrl();
        }

        return $this->articleUrl;
    }

    protected function setArticleImageUrl(){
        $this->articleImageUrl = ImageHelper::getResizedImageUrl($this->getLeadImageUrl(), '660x300', 100 ,1);
    }

    public function getArticleImageUrl(){

        if(empty($this->articleImageUrl)){
            $this->setArticleImageUrl();
        }

        return $this->articleImageUrl;
    }

    protected function getToolsAndMaterials() {
        $toolsAndMaterials = $this->resource['article']['content']['tools_and_materials']['value'];
        $toolsAndMaterials = strip_tags($toolsAndMaterials, '<li>,<a>');
        $toolsAndMaterials = explode('</li>', $toolsAndMaterials);
        $arrToolsAndMaterials = array();
        foreach($toolsAndMaterials as $toolsAndMaterial){
            $toolsAndMaterial = trim(strip_tags($toolsAndMaterial,'<a>'));
            if(strpos($toolsAndMaterial,'doityourself.com')) {
                $arrToolsAndMaterials[] = preg_replace('#<a.*?>(.*?)</a>#i', '\1', $toolsAndMaterial);
            } else {
                $arrToolsAndMaterials[] = $toolsAndMaterial;
            }
        }
        return $arrToolsAndMaterials;
    }

    protected function getRelatedArticlesFromSolr($article_id = '') {
        $arrfq = array('type:(How-To OR Slideshow)', 'has_lead_image_int:1');
        $fq = "+" . implode(' +', $arrfq);

        $arrQuery = array(
            'fq' => $fq
        );

        $response = (new SolrMoreLikeThisRequest())->setQuery('id:'.$article_id, $arrQuery)->process()->getValidatedResponse();
        $response = SolrMoreLikeThisResource::make($response)->resolve();
        return $response;
    }

    protected function getRelatedArticles() {
        $allRelatedArticles = !empty($this->resource['related_articles']) ?
            $this->resource['related_articles'] :
            $this->getRelatedArticlesFromSolr($this->resource['request']['article_id'])['response'];

        //found some cases with empty reponse from sde for releated articles
        if (empty($allRelatedArticles)){
            return [] ;
        }

        $relatedArticles = [];
        if(empty($allRelatedArticles)){
            return $relatedArticles;
        }

        $total = min(self::relatedArticlesLimit, count($allRelatedArticles));
        for ($count = 0; $count < $total; ++$count) {
            $relatedArticle = $allRelatedArticles[$count];
            if (isset($relatedArticle['image']['url']) && !empty($relatedArticle['image']['url'])){
                $imgUrl = $relatedArticle['image']['url'];
            } elseif (isset($relatedArticle['imgUrl']) && !empty($relatedArticle['imgUrl'])){
                $imgUrl = $relatedArticle['imgUrl'];
            } elseif (isset($relatedArticle['image_location'][0]) && !empty($relatedArticle['image_location'][0])) {
                $imgUrl = $relatedArticle['image_location'][0];
            }

            if(!empty($imgUrl)){
                $image = ImageHelper::getResizedImageUrl($imgUrl, '80x80', 85, 1);
            } else {
                $image = "/images/placeholders/default_80x80.png";
            }

            $article['url'] = SequoiaUrlHelper::replace($relatedArticle['url']);
            $article['title_40'] = substr($relatedArticle['title'],0,40);
            $article['title_40'] = strlen($relatedArticle['title']) > 40 ? $article['title_40'].'...' : $article['title_40'];
            $article['title_100'] = substr($relatedArticle['title'],0,100);
            $article['title_100'] = strlen($relatedArticle['title']) > 100 ? $article['title_100'].'...' : $article['title_100'];
            $article['image'] = $image;
            if (isset($relatedArticle['section']['url']) && !empty($relatedArticle['section']['url'])) {
                $article['section_url'] = SequoiaUrlHelper::replace($relatedArticle['section']['url']);
            }
            if (isset($relatedArticle['section']['title']) && !empty($relatedArticle['section']['title'])) {
                $article['section_title'] = $relatedArticle['section']['title'];
            } elseif (isset($relatedArticle['section'][0]) && !empty($relatedArticle['section'][0])) {
                $article['section_title'] = $relatedArticle['section'][0];
            }
            $article['GAClickCategory']=self::relatedArticleWidgetGACategory;
            $article['GAClickLabel']=self::relatedArticleWidgetGALabel.($count+1);
            array_push($relatedArticles, $article);
        }

        return $relatedArticles;
    }

    protected function getContentGroups(){
        return collect([
            'contentGroup1'=>$this->getCategoryHierarchy($this->resource['article']['breadcrumbs']['primary']),
            'contentGroup2'=>(!empty($this->resource['article']['custom_fields']['video_id']['value'])) ? $this->resource['article']['custom_fields']['video_id']['value']:''
        ]);
    }

    public function getCategoryHierarchy($breadcrumbs){
        $strCategoryHierachy='/';
        foreach( $breadcrumbs  as $category){
            $strCategoryHierachy.=$category['basename'].'/';
        }
        return $strCategoryHierachy;
    }


    protected function getArticleBreadcrumbList(){
        $count = 0;

        $breadcrumbList = '<ul class="breadcrumbs hidden-xs">';
        $breadcrumbList .= '<li class="breadcrumbs-item ">';
        $breadcrumbList .= '<a class="breadcrumbs-item__link" href="'.url("/").'">Home</a>';
        $breadcrumbList .= '</li>';

        $breadcrumbs = $this->resource['article']['breadcrumbs']['primary'] ;
        if(!empty($breadcrumbs)){
            foreach ($breadcrumbs as $item) {

                $breadcrumbList .= ' / ';

                $breadcrumbList .= '<li class="breadcrumbs-item ">';
                $breadcrumbList .= '<a class="breadcrumbs-item__link" href="' . SequoiaUrlHelper::replace($item['url']) . '">' . $item['title'] . '</a>';
                $breadcrumbList .= '</li>';

                if ($count < sizeof($this->categoryLevelType) && !empty($this->categoryLevelType[$count])) {
                    $targetType = $this->categoryLevelType[$count++];
                    $this->categoryTargets[$targetType] = str_replace(array(",", "'"), '', $item["title"]);
                }
            }
        }

        $breadcrumbList .= '</ul>';
        return $breadcrumbList;
    }

    protected function getLeadImageUrl(){
        return $this->resource['article']['content']['lead_image']['url'];
    }

    protected function getTagList(){
        $tagList = '<ul class="tags">';
        $tags = $this->resource['article']['tags']['content'];
        foreach ($tags as $item){
            $tagList .= '<li class="tag-item">';
            $tagList .= '<a class="btn btn-primary" href="'.SequoiaUrlHelper::replace(str_replace("content/tag/","topics/",$item['url'])).'">'.ucwords($item['tag']).'</a>';
            $tagList .= '</li>';
        }
        $tagList .= '</ul>';
        return $tagList;
    }

    //todo: needs to improve legacy code
    /* setContentImages
     * Updates the image tags within the article content with new image tags and data attributes
     * return void
     */
    protected function getArticleContent()
    {
        if (!empty($this->resource['article']['content']['article_body']['value'])) {
            $dom = new DOMDocument();
            libxml_use_internal_errors(true);
            $dom->loadHTML('<?xml encoding="utf-8" ?>' . $this->resource['article']['content']['article_body']['value']);
            $dom = $this->embedVideo($dom);
            foreach ($dom->getElementsByTagName('img') as $img) {
                if (!empty($img->getAttribute('src')) && strpos($img->getAttribute('src'), 'data') !== 0) {
                    $span = $dom->createElement('span', '');
                    $this->updateContentImages($dom, $span, $img);
                    $img->parentNode->replaceChild($span, $img);
                }
            }
            return $dom->saveHTML();
        }
    }

    protected function embedVideo($dom)
    {
        if (isset($this->resource['article']['custom_fields']['video_id']) && !empty($this->resource['article']['custom_fields']['video_id']['value'])) {
            $this->setVideoAttributes('article-video', $this->resource['article']['custom_fields']['video_id']['value']);
            return $this->updateDom($dom);
        } else if (isset($this->resource['article']['custom_fields']['playlist_id']) && !empty($this->resource['article']['custom_fields']['playlist_id']['value'])) {
            $this->setVideoAttributes('article-playlist', $this->resource['article']['custom_fields']['playlist_id']['value']);
            return $this->updateDom($dom);
        }
        return $dom;
    }

    protected function setVideoAttributes($tagName, $id){
        $this->videoId = $id;
        $this->videoTagName = $tagName;
    }

    protected function updateDom($dom){
        foreach ($dom->getElementsByTagName('p') as $key => $paragraph) {
            if ($key == 0 && str_word_count($paragraph->textContent) >= 73 || $key == 1) {
                $video = $dom->createElement($this->videoTagName, '');
                $video->setAttribute('id', $this->videoId);
                $paragraph->parentNode->insertBefore($video, $paragraph->nextSibling);
                break;
            }
        }
        return $dom;
    }

    protected function getPrevNextSteps(){
        $steps = array();
        foreach ($this->resource['steps'] as $step){

            if ($step['follow'] == 'NEXT'){
                $steps['next'] = $step;
                continue;
            }

            if ($step['follow'] == 'PREV'){
                $steps['prev'] = $step;
                continue;
            }
        }
        return $steps;
    }

    /* updateContentImages
     * Creates a new image tag with data attributes for original image size, cropped size and mobile size
     * and its container base off existing image tags
     * returns void
     */
    protected function updateContentImages($oDom, $oSpan, $oImage){
        $strOriginalImage=$oImage->getAttribute('src');
        $strAltText=$oImage->getAttribute('alt');
        while ($oImage->attributes->length) {
            $oImage->removeAttribute($oImage->attributes->item(0)->name);
        }

        if(preg_match('~https?:\/\/[A-Za-z0-9\/\.\_\-]+\/([0-9]+)x([0-9]+)~',$strOriginalImage,$arrImageDimension)){
            $numWidth=$arrImageDimension[1];
            $numHeight=$arrImageDimension[2];
            $strCropImage=$this->setContentCroppedImage($strOriginalImage, $numWidth,$numHeight);
            if($arrImageDimension[1]<320){
                $strMobileImage=$strOriginalImage;
            }else{
                $strMobileImage=$this->setContentMobileImage($strOriginalImage, $numWidth,$numHeight);
            }

            $oImage->setAttribute('data-crop-url',$strCropImage);
            $oImage->setAttribute('data-mobile-url',$strMobileImage);
            $oImage->setAttribute('data-original-url',$strOriginalImage);
            $oImage->setAttribute('data-target','#articlesModal');
            $oImage->setAttribute('src','');
        } else{
            $numWidth=660;
            $numHeight=300;
            $oImage->setAttribute('data-crop-url',$strOriginalImage);
            $oImage->setAttribute('data-mobile-url',$strOriginalImage);
            $oImage->setAttribute('data-original-url',$strOriginalImage);
            $oImage->setAttribute('data-target','#articlesModal');
            $oImage->setAttribute('src','');
        }


        if($numWidth>320){
            $oImage->setAttribute('class','js-mobile-stretch');
        }else{
            $oImage->setAttribute('data-mobile-height',$numHeight);
        }
        if(!empty($strAltText)){
            $oImage->setAttribute('alt',$strAltText);
        }

        $oSpan->setAttribute('class','image-container');
        $oCloneImage=clone $oImage;
        $oSpan->appendChild($oCloneImage);

        if($numWidth>600||$numHeight>300){
            if(($numWidth/$numHeight)!=(660/300)){
                $oExpandableSpan=$oDom->createElement('span','');
                $oExpandableSpan->setAttribute('class','cs-image-expandable');
                $oExpandableSpan->setAttribute('data-target','#articlesModal');
                $oExpandableElement=$oDom->createElement('i','');
                $oExpandableElement->setAttribute('class','fa fa-arrows-alt');
                $oExpandableElement->setAttribute('aria-hidden','true');
                $oExpandableSpan->appendChild($oExpandableElement);
                $oSpan->appendChild($oExpandableSpan);

                $oOverlaySpan=$oDom->createElement('span','');
                $oOverlaySpan->setAttribute('class','cs-image-overlay');
                $oOverlaySpan->setAttribute('data-target','#articlesModal');
                $oPointerElement=$oDom->createElement('i','');
                $oPointerElement->setAttribute('class','fa fa-hand-pointer-o');
                $oPointerElement->setAttribute('aria-hidden','true');
                $oOverlaySpan->appendChild($oPointerElement);
                $oSpan->appendChild($oOverlaySpan);
            }
        }
    }

    /* setContentMobileImage
    * Resize images for mobile
    * return string imageUrl
    */
    public function setContentMobileImage($strOriginalImage,$numImageWidth, $numImageHeight){
        $numImageRatio=$this->_iMobileContentImageWidth/$numImageWidth;
        $numMobileImageHeight=ceil($numImageRatio*$numImageHeight);
        return $this->getImageUrl($strOriginalImage,$this->_iMobileContentImageWidth.'x'.$numMobileImageHeight,$this->_iImageQuality);
    }

    /* setContentCroppedImages
     * Determine whether and image needs to be cropped for desktop/tablet devices and crops it if necessary
     * return string cropped version of URL
     */
    public function setContentCroppedImage($strOriginalImage,$numImageWidth, $numImageHeight){
        if($numImageWidth>=$this->_iContentImageMinWidth && $numImageHeight>=$this->_iContentImageHeight){
            $strCropImage=$this->getImageUrl($strOriginalImage,$this->_iContentImageWidth.'x'.$this->_iContentImageHeight,$this->_iImageQuality,'1');
        }else if($numImageHeight>=$this->_iContentImageHeight){
            $strCropImage=$this->getImageUrl($strOriginalImage,$numImageWidth.'x'.$this->_iContentImageHeight,$this->_iImageQuality,'1');
        }else if($numImageWidth>=$this->_iContentImageMinWidth){
            $strCropImage=$this->getImageUrl($strOriginalImage,$this->_iContentImageWidth.'x'.$numImageHeight,$this->_iImageQuality,'1');
        }else{
            $strCropImage=$strOriginalImage;
        }
        return $strCropImage;
    }

    public function getImageUrl($strUrl,$strDimension = null, $iQuality = null, $strMode = '1', $bAddImgBase = false, $strImageBase = null){
        if (empty($strUrl)) return $strUrl;
        $strDimension = !empty($strDimension) ? $strDimension:$this->_strHeroImageSize;
        $iQuality = !empty($iQuality) ? $iQuality : $this->_iMaxQuality;
        $iQuality = ($iQuality > $this->_iMaxQuality) ? $this->_iMaxQuality : $iQuality;
        if ($bAddImgBase){
            $strBaseImage = (empty($strImageBase))? $this->_strImageBaseServer:$strImageBase;
            $strRequest = preg_replace('#^(.*)/cimg(.*)/www.doityourself.com/(.*)#i', '/cimg$2/www.doityourself.com/$3',$strUrl);
        } else {
            $strBaseImage = null;
            $strRequest = $strUrl;
        }
        $strImageUrl = $this->getResizedImageUrl($strRequest, $strDimension, $iQuality, $strMode, $strBaseImage);
        if(strpos($strImageUrl,'https') === false ) {
            $strImageUrl = str_replace("http://", "https://", $strImageUrl);
        }
        return $strImageUrl;
    }
    protected function getResizedImageUrl($strImageUri, $strDimension = null, $strQuality = null, $strMode = null, $strImgBase = null) {

        if (isset($strDimension) && isset($strQuality)) {
            $strSearch = 'original';
            //is photo profile url?
            if(strpos($strImageUri,'/cimg/profiles/') !== false){
                if(strpos($strImageUri,'/size_quality/')){
                    $strSearch = 'size_quality';
                }
            } else if(strpos($strImageUri,'/original/max/')) {
                $strSearch .= '/max';
                $strQuality = $this->_iMaxQuality;
            }else if(preg_match('~https?:\/\/[A-Za-z0-9\/\.\_]+\/([0-9]+x[0-9]+_[0-9]+\-[0-9]+|[0-9]+x[0-9]+_[0-9]+)~',$strImageUri,$matches)){
                $strSearch=$matches[1];
            }
            $strReplace = $strDimension . '_' . $strQuality;
            $strReplace .= !empty($strMode) ? '-'.$strMode : '';
            $pattern = '#^(.*)/('.$strSearch.')/(.*)#i';
            $strImageUri = preg_replace($pattern, '$1/'.$strReplace.'/$3', $strImageUri);
        }

        $strImageUrl = (!empty($strImgBase)) ? $strImgBase.$strImageUri : $strImageUri;
        return $strImageUrl;
    }

    protected function getSlideShowSlides(){

        $slides = array();
        foreach($this->resource['article']['content']['slideshow']['value']['slides'] as $index=>$slide) {
            $slides[]= [
                "Order" => $index,
                "title_slug" => $slide['title_slug'],
                "title" => $slide['title'],
                "image" => !empty($slide['image']['url']) ?
                    ImageHelper::getResizedImageUrl($slide['image']['url'], '660x300', 100, 1) : "",
                "imageThumb" => !empty($slide['image']['url']) ?
                    ImageHelper::getResizedImageUrl($slide['image']['url'], '80x80', 100, 1) : "",
                "link" => "",
                "slide_content" => $slide['body'],
                "content_id" => $slide['token'],
                "class" => ".galleria-class-" . $this->resource['article']['article_id'],
                'content_class' => '.galleria-content-' . $this->resource['article']['article_id'],
                "html" => ""
            ];
        }

        return $slides;
    }

    protected function getStructureData(){
        $articleStructureData = [
            '@context'=>'https://schema.org',
            '@type'=>'Article',
            'mainEntityOfPage'=>[
                '@type'=>'WebPage',
                '@id'=>$this->getArticleUrl()
            ],
            'headline'=>$this->resource['article']['title'],
            'description'=>$this->resource['article']['meta_description'],
            //        'articleBody'=>$strArticleBody,
            //        'wordCount'=>str_word_count($strArticleBody),
            'publisher'=>array(
                '@type'=>'Organization',
                'name'=>'DoItYourself.com',
                'logo'=>[
                    '@type'=>'ImageObject',
                    'url'=> asset("images/diy-logo.png")
                ]
            ),
            'step'=>$this->getStepsWithCrawl(),
            'totalTime'=>$this->articleTotalName(),
        ];

        if(!empty($this->resource['article']['content']['lead_image'])){
            $articleStructureData['image']= $this->getArticleImageUrl();
        }

        if(!empty($this->resource['article']['author']['name'])){
            $articleStructureData['author']= [
                '@type'=>'Person',
                'name'=> $this->resource['article']['author']['name']
            ];
        }

        return json_encode($articleStructureData);
    }

    protected function hasModernizeScript(){
        return in_array($this->resource['article']['article_id'],config('modernize.articles'));
    }

    protected function getEstimatedCost(){
        return (isset($this->resource['article']['content']['estimated_cost']['value']) and !empty($this->resource['article']['content']['estimated_cost']['value']))?
            $this->resource['article']['content']['estimated_cost']['value']:'';
    }

    protected function getTotalTime(){
        return (isset($this->resource['article']['content']['total_time']['value']) and !empty($this->resource['article']['content']['total_time']['value']))?
            $this->resource['article']['content']['total_time']['value']:'';
    }

    protected function getLevelDifficulty(){
        return (isset($this->resource['article']['content']['level_difficulty']['value']) and !empty($this->resource['article']['content']['level_difficulty']['value']))?
            $this->resource['article']['content']['level_difficulty']['value']:'';
    }

    /**
     * Crawl steps from content. this is the plan B for short term
     * @param string $content
     */
    public function getStepsWithCrawl(){
        $steps=array();
        $dom = new DOMDocument();
        $dom->loadHTML($this->getArticleContent());
        foreach ($dom->getElementsByTagName('h4') as $tag) {
            if (!empty($tag->nodeValue)){
                $title=$tag->nodeValue;
                $description=$tag->nextSibling->nextSibling->nodeValue;
                array_push($steps,array(
                    '@type'=>'HowToStep',
                    'name'=>$title,
                    'text'=>$description,
                    'url'=>$this->articleUrl
                ));
            }
        }
        return $steps;
//        var_dump($steps);
    }

    public function feature001(){
        //TODO
    }
    public function feature002(){
        //Todo
    }
    public function feature003(){
        //Todo
        echo "commit01";
    }
    public function featureNewCommit03(){
        //Todo
        echo "commit03";
    }


}
