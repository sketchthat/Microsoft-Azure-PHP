<?php
    namespace Sketchthat\Service\Azure;

    use Symfony\Component\HttpFoundation\Request;
    use Symfony\Component\HttpFoundation\Response;
    use Symfony\Component\HttpFoundation\RedirectResponse;

    use Symfony\Component\HttpKernel\Exception\HttpException;
    use Symfony\Component\HttpFoundation\StreamedResponse;
    use Symfony\Component\HttpFoundation\ResponseHeaderBag;

    use WindowsAzure\Common\ServicesBuilder;
    use WindowsAzure\Blob\Models\PublicAccessType;
    use WindowsAzure\Blob\Models\CreateContainerOptions;

    class AzureHelper {
        protected $azureProxy;
        protected $containerPrefix;

        public function __construct($protocol, $accountName, $accountKey, $containerPrefix) {
            $azureConnectionString = 'DefaultEndpointsProtocol='.$protocol.';AccountName='.$accountName.';AccountKey='.$accountKey;

            $this->azureProxy = ServicesBuilder::getInstance()->createBlobService($azureConnectionString);

            $this->containerPrefix = $containerPrefix;
        }

        public function createContainer($container, $publicAccess = PublicAccessType::BLOBS_ONLY) {
            if(!preg_match('/^(?!-)(?!.*--)[a-z0-9-]{3,63}+(?<!-)$/', $container)) {
                throw new HttpException(400, 'Invalid Container Name');
            }

            $container_lists = $this->listContainers('');

            foreach($container_lists as $container_list) {
                if($container_list->getName() == $container) {
                    return false;
                }
            }

            $createContainerOptions = new CreateContainerOptions();
            $createContainerOptions->setPublicAccess($publicAccess);

            try {
                $this->azureProxy->createContainer($container, $createContainerOptions);

                return true;
            } catch(ServiceException $e){
                $code = $e->getCode();
                $error_message = $e->getMessage();

                throw new HttpException($code, $error_message);
            }
        }

        public function listContainers($container) {
            if(!is_null($container) && $container != '') {
                return array();
            }

            try {
                $container_list = $this->azureProxy->listContainers();

                return $container_list->getContainers();
            } catch(ServiceException $e){
                $code = $e->getCode();
                $error_message = $e->getMessage();

                throw new HttpException($code, $error_message);
            }
        }

        public function listBlobs($container) {
            if(is_null($container) || $container == '') {
                return array();
            }

            try {
                $blob_list = $this->azureProxy->listBlobs($container);

                return $blob_list->getBlobs();
            } catch(ServiceException $e){
                $code = $e->getCode();
                $error_message = $e->getMessage();

                throw new HttpException($code, $error_message);
            }
        }

        public function uploadBlob($container, $blob_name, $original_file) {
            try {
                $content = fopen($original_file, 'r');

                return $this->azureProxy->createBlockBlob($container, $blob_name, $content);
            } catch(ServiceException $e){
                $code = $e->getCode();
                $error_message = $e->getMessage();

                throw new HttpException($code, $error_message);
            }
        }

        public function existsBlob($container, $blob) {
            $blob_list = $this->listBlobs($container);

            foreach($blob_list as $blob_file) {
                if($blob_file->getName() == $blob) {
                    return true;
                }
            }

            return false;
        }

        public function downloadBlob($container, $blob) {
            try {
                $blob_get = $this->azureProxy->getBlob($container, $blob);

                $response = new StreamedResponse();
                $response->setCallback(function () use ($blob_get) {
                    fpassthru($blob_get->getContentStream());
                });

                $content = $response->headers->makeDisposition(ResponseHeaderBag::DISPOSITION_ATTACHMENT, $blob);
                $response->headers->set('Content-Disposition', $content);
                $response->send();
            } catch(ServiceException $e){
                $code = $e->getCode();
                $error_message = $e->getMessage();

                throw new HttpException($code, $error_message);
            }
        }

        public function deleteBlob($container, $blob) {
            try {
                $this->azureProxy->deleteBlob($container, $blob);
            } catch(ServiceException $e){
                $code = $e->getCode();
                $error_message = $e->getMessage();

                throw new HttpException($code, $error_message);
            }
        }

        public function deleteContainer($container) {
            try {
                $this->azureProxy->deleteContainer($container);
            } catch(ServiceException $e){
                $code = $e->getCode();
                $error_message = $e->getMessage();

                throw new HttpException($code, $error_message);
            }
        }

        public function addContainerPrefix($container) {
            return $this->containerPrefix.$container;
        }
    }