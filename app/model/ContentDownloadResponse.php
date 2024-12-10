<?php

namespace App\Model;

use Nette;

class ContentDownloadResponse implements Nette\Application\IResponse
{
    /** @var string */
    private $content;
    /** @var string */
    private $contentType;
    /** @var string */
    private $name;
    /**
     * @param  string  content
     * @param  string  user name name
     * @param  string  MIME content type
     */
    public function __construct($content, $name, $contentType = null) {
        $this->content = $content;
        $this->name = $name;
        $this->contentType = $contentType ? $contentType : 'application/octet-stream';
    }
    /**
     * Returns the content of downloaded file.
     * @return string
     */
    final public function getContent() {
        return $this->content;
    }
    /**
     * Returns the file name.
     * @return string
     */
    final public function getName() {
        return $this->name;
    }
    /**
     * Returns the MIME content type of an downloaded file.
     * @return string
     */
    final public function getContentType() {
        return $this->contentType;
    }
    /**
     * Sends response to output.
     * @return void
     */
    public function send(Nette\Http\IRequest $httpRequest, Nette\Http\IResponse $httpResponse) {
        $httpResponse->setContentType($this->contentType);
        $httpResponse->setHeader('Pragma', "public");
        $httpResponse->setHeader('Expires', 0);
        $httpResponse->setHeader('Cache-Control', "must-revalidate, post-check=0, pre-check=0");
        $httpResponse->setHeader('Content-Transfer-Encoding', "binary");
        $httpResponse->setHeader('Content-Description', "File Transfer");
        $httpResponse->setHeader('Content-Length', mb_strlen($this->content));
        $httpResponse->setHeader('Content-Disposition', 'attachment; filename="' . $this->name . '"');
        echo $this->content;
    }
}
