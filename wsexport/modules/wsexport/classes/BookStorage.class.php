<?php
/**
* @package   wsexport
* @subpackage wsexport
* @author Thomas Pellissier Tanon
* @copyright 2011 Thomas Pellissier Tanon
* @licence http://www.gnu.org/licenses/gpl.html GNU General Public Licence
*/

/**
 * Abstraction of the book stockage
 * @package   wsexport
 * @subpackage wsexport
 */
class BookStorage {
        /**
         * get the book
         * @param $lang string the language code of the book
         * @param $title string the title of the book
         * @param $withPictures bool include the pictures in the book
         * @return Book
         * @todo storage in the db
         */
        public function get($lang, $title, $withPictures = true) {
                return $this->getApi($lang, $title, $withPictures);
        }

        /**
         * get metadatas of a book
         * @param $lang string the language code of the book
         * @param $title string the title of the book
         * @return BookRecord
         * @todo categories
         */
        public function getMetadata($lang, $title) {
      	        $bookDao = jDao::get('book')->get(array($lang, $title));
		if($bookDao == null)
        		throw new HttpException('Not Found', 404);
                return $this->getBook($bookDao);
        }

        /**
         * get metadatas of books
         * @param $params array|string the search params like 'property' => 'value'
         * @param $order string the title of the book
         * @param $wayAsc bool if the search sort ascendently.
         * @param $itemPerPage integer number of result to return
         * @param $offset integer index of the current result
         * @return array 0 => number of results, 1 => array|BookRecord
         * @todo categories
         */
        public function getMetadatas($params, $order = '', $wayAsc = true, $itemPerPage = 20, $offset = 0) {
                $books = array();
                $conditions = jDao::createConditions();
                foreach($params as $id => $value) {
                        $conditions->addCondition($id, 'LIKE', $value);
                }
                if($order != '') {
                        if($wayAsc)
                                $conditions->addItemOrder($order, 'ASC');
                        else
                                $conditions->addItemOrder($order, 'DESC');

                }
                $count = jDao::get('book')->countBy($conditions);
                $liste = jDao::get('book')->findBy($conditions, $offset, $itemPerPage);
                foreach($liste as $bookDao) {
      	                $books[] = $this->getBook($bookDao);
                }
                return array($count, $books);
        }

        /**
         * serach in metadatas of books
         * @param $lang string the lang of the wikisource
         * @param $query string the query
         * @param $itemPerPage integer number of result to return
         * @param $offset integer index of the current result
         * @return array 0 => number of results, 1 => array|BookRecord
         */
        public function searchMetadatas($lang, $query, $itemPerPage = 20, $offset = 0) {
                $books = array();
                $count = jDao::get('book')->searchCount($lang, '%' . $query . '%');
                $liste = jDao::get('book')->search($lang, '%' . $query . '%', $offset, $itemPerPage);
                foreach($liste as $bookDao) {
      	                $books[] = $this->getBook($bookDao);
                }
                return array($count, $books);
        }

        /**
         * update metadata of the book to the database
         * @param $lang string the lang of the wikisource
         * @param $cat string the category
         */
        public function setMetadataFromCategory($lang, $cat) {
                $api = new Api($lang);
                $response = $api->completeQuery(array('generator' => 'categorymembers', 'gcmtitle' => $cat, 'gcmnamespace' => '0', 'prop' => 'info', 'gcmlimit' => '100'));
                if(!array_key_exists('query', $response))
                        throw new HttpException('Not Found', 404);
                $pages = $response['query']['pages'];
                foreach($pages as $page) {
                        $title = str_replace(' ', '_', $page['title']);
      	                $bookDao = jDao::get('book')->get(array($lang, $title));
		        if($bookDao == null)
        		        $this->createMetadata($lang, $title, $page['lastrevid']);
                        else if($bookDao->lastrevid != $page['lastrevid'])
                                $this->updateMetadata($lang, $title, $page['lastrevid']);
                }
        }

        /**
         * add metadata of the book to the database
         * @param $lang the lang of the wikisource
         * @param $title string the title of the book
         * @param $lastrevid integer the last revision id of the main page in Wikisource
         */
        public function createMetadata($lang, $title, $lastrevid = 0) {
                $book = $this->getApiMetadata($lang, $title);
                $bookFactory = jDao::get('book');
                $bookDao = $this->getBookRecord($book);
                $bookDao->uuid = $book->uuid;
                $bookDao->lastrevid = $lastrevid;
                $bookFactory->insert($bookDao);
        }

        /**
         * update metadata of the book to the database
         * @param $lang the lang of the wikisource
         * @param $title string the title of the book
         * @param $lastrevid integer the last revision id of the main page in Wikisource
         */
        public function updateMetadata($lang, $title, $lastrevid = 0) {
                $book = $this->getApiMetadata($lang, $title);
                $bookFactory = jDao::get('book');
                $bookDao = $bookFactory->get(array($lang, $title));
                $bookDao = $this->updateBookRecord($bookDao, $book);
                $bookDao->lastrevid = $lastrevid;
                $bookFactory->update($bookDao);
        }

        /**
         * delete metadata of the book to the database
         * @param $lang the lang of the wikisource
         * @param $title string the title of the book
         */
        public function deleteMetadata($lang, $title) {
                $bookFactory = jDao::get('book');
                $bookFactory->delete(array($lang, $title));
        }

        /**
         * add metadata of the book to the database
         * @param $lang the lang of the wikisource
         * @param $title string the title of the book
         */
        public function incrementDownload($lang, $title) {
                $bookFactory = jDao::get('book');
                $bookDao = $bookFactory->get(array($lang, $title));
                $bookDao->downloads++;
                $bookFactory->update($bookDao);
        }


        /**
         * get metadatas of a book from Wikisource
         * @param $lang the lang of the wikisource
         * @param $title string the title of the book
         * @param $withPictures bool include the pictures in the book
         * @return Book
         */
        protected function getApi($lang, $title, $withPictures = true) {
                $api = new Api($lang);
      	        $provider = new BookProvider($api, $withPictures);
                return $provider->get($title);
        }

        /**
         * get metadatas of a book from Wikisource
         * @param $lang the lang of the wikisource
         * @param $title string the title of the book
         * @return Book
         */
        protected function getApiMetadata($lang, $title) {
                $api = new Api($lang);
      	        $provider = new BookProvider($api);
                return $provider->get($title, true);
        }

        /**
         * get book objet from book record
         * @param $bookDao jDaoRecordBase
         * @return BookRecord
         * @todo categories
         */
        protected function getBook(jDaoRecordBase $bookDao) {
                $book = jClasses::create('BookRecord');
                $book->uuid = $bookDao->uuid;
                $book->title = $bookDao->title;
                $book->lang = $bookDao->lang;
                $book->type = $bookDao->type;
                $book->name = $bookDao->name;
                $book->author = $bookDao->author;
                $book->translator = $bookDao->translator;
                $book->illustrator = $bookDao->illustrator;
                $book->school = $bookDao->school;
                $book->publisher = $bookDao->publisher;
                $book->year = $bookDao->year;
                $book->place = $bookDao->place;
                $book->key = $bookDao->key;
                $book->progress = $bookDao->progress;
                $book->volume = $bookDao->volume;
                $book->categories = array();
                $book->created = $bookDao->created;
                $book->updated = $bookDao->updated;
                $book->downloads = $bookDao->downloads;
                return $book;
        }

        /**
         * get book record from book object
         * @param $book Book
         * @return jDaoRecordBase
         */
        protected function getBookRecord(Book $book) {
                $bookDao = jDao::createRecord('book');
                return $this->updateBookRecord($bookDao, $book);
        }

        /**
         * udate a book record from data of a book object
         * @param $bookDao jDaoRecordBase
         * @param $book Book
         * @return jDaoRecordBase
         */
        protected function updateBookRecord(jDaoRecordBase $bookDao, Book $book) {
                $bookDao->title = $book->title;
                $bookDao->lang = $book->lang;
                $bookDao->type = $book->type;
                $bookDao->name = $book->name;
                $bookDao->author = $book->author;
                $bookDao->translator = $book->translator;
                $bookDao->illustrator = $book->illustrator;
                $bookDao->school = $book->school;
                $bookDao->publisher = $book->publisher;
                $bookDao->year = $book->year;
                $bookDao->place = $book->place;
                $bookDao->key = $book->key;
                $bookDao->progress = $book->progress;
                $bookDao->volume = $book->volume;
                return $bookDao;
        }
}
